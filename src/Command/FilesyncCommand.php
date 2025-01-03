<?php
declare(strict_types=1);

/*
 * 	This File is part of Toteph42 FilesyncBundle
 *
 *	@copyright	(c) 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	https://github.com/toteph42/filesync/blob/master/LICENSE
 */

namespace Toteph42\FilesyncBundle\Command;

use Contao\CoreBundle\Filesystem\Dbafs\ChangeSet\ChangeSet;
use Contao\CoreBundle\Filesystem\Dbafs\DbafsManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Model\Collection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Contao\FilesModel;
use Contao\MemberGroupModel;
use Contao\MemberModel;
use Contao\FormModel;
use Contao\FormFieldModel;
use function Safe\array_flip;

#[AsCommand(
    name: 'toteph42:filesync',
    description: 'Synchronizes the registered DBAFS with the virtual filesystem and notify users.',
)]
class FilesyncCommand extends Command
{
    public function __construct(
    		private readonly DbafsManager $dbafsManager,
    		private readonly ContaoFramework $framework)
    {
    	parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
		$output->writeln('Synchronizing…');

        $time = microtime(true);
        $changeSet = $this->dbafsManager->sync(...$input->getArgument('paths'));
        $timeTotal = round(microtime(true) - $time, 2);

        // send notification
        $this->notify($changeSet, $output);

        $this->renderStats($changeSet, $output);

        (new SymfonyStyle($input, $output))->success("Synchronization complete in {$timeTotal}s.");

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addArgument('paths', InputArgument::IS_ARRAY, 'Optional path(s) for partial synchronization.');
    }

    private function renderStats(ChangeSet $changeSet, OutputInterface $output): void
    {
    	if ($changeSet->isEmpty())
        {
            $output->writeln('No changes.');
            return;
        }

     	$table = new Table($output);
        $table->setHeaders(['Action', 'Resource / Change']);

        $output->getFormatter()->setStyle('hash', new OutputFormatterStyle('yellow'));
        $output->getFormatter()->setStyle('newpath', new OutputFormatterStyle('green'));
        $output->getFormatter()->setStyle('oldpath', new OutputFormatterStyle('red'));

        foreach ($changeSet->getItemsToCreate() as $itemToCreate) {
            $table->addRow([
                'add',
                "<newpath>{$itemToCreate->getPath()}</newpath> (new hash: <hash>{$itemToCreate->getHash()}</hash>)",
            ]);
        }

        foreach ($changeSet->getItemsToUpdate() as $itemToUpdate) {
            if ($itemToUpdate->updatesPath()) {
                $change = "{$itemToUpdate->getExistingPath()} → <newpath>{$itemToUpdate->getNewPath()}</newpath>";
                $action = 'move';
            } else {
                $change = $itemToUpdate->getExistingPath();
                $action = 'update';
            }

            if ($itemToUpdate->updatesHash()) {
                $change .= " (updated hash: <hash>{$itemToUpdate->getNewHash()}</hash>)";
            }

            $table->addRow([$action, $change]);
        }

        foreach ($changeSet->getItemsToDelete() as $itemToDelete) {
            $table->addRow(['delete', "<oldpath>{$itemToDelete->getPath()}</oldpath>"]);
        }

        $table->render();

        $output->writeln(
            \sprintf(
                ' Total items added: %s | updated/moved: %s | deleted: %s',
                \count($changeSet->getItemsToCreate()),
                \count($changeSet->getItemsToUpdate()),
                \count($changeSet->getItemsToDelete()),
            ),
        );
    }

    private function notify(ChangeSet $changeSet, OutputInterface $output): void
    {

		$output->writeln('Checking for file notification…');

    	// initialize framework
        $this->framework->initialize();

        // get all changes
	   	$all_files = [];

    	// get list of all files
    	foreach ($changeSet->getItemsToCreate()+$changeSet->getItemsToUpdate() as $item)
			$all_files[] = substr(strpos(get_class($item), 'ItemToCreate') ? $item->getPath() : $item->getExistingPath(), 6);

       	// sort out user files
        $usr_files = [];
	   	$users = MemberModel::findAll();
	   	foreach ($users as $usr)
	   	{
	   		$usr_files[$usr->id] = [];

	   		// home directory set?
			if ($usr->homeDir && ($obj = FilesModel::findByUuid($usr->homeDir)))
			{
				foreach ($all_files as $k => $file)
				{
					$path = substr($obj->path, 6);
					if (str_contains($file, $path) !== false)
					{
						$usr_files[$usr->id][] = $file;
						unset($all_files[$k]);
					}
				}
			}
	   	}

	   	// sort out group files
    	$grp_files = [];
    	foreach (MemberGroupModel::findAll() as $item)
    	{
	   		$grp_files[$item->id] = [];

 			// try to find form for group
    		if (!($form = FormModel::findByTitle($item->name)) || $form->format != 'email')
   				continue;
			$grp_files[$item->id]['form'] = $form;

  			foreach ($all_files as $k => $file)
			{
				if (str_contains($file, $item->name))
				{
					$grp_files[$item->id][] = substr($file, strlen($item->name) + 1);
					unset($all_files[$k]);
				}
			}
    	}

		// get transport
		$transport = Transport::fromDsn($_SERVER['MAILER_DSN']);
		$mailer = new Mailer($transport);

		// notify user
	   	foreach ($users as $usr)
	   	{
	   		// disabled?
	   		if ($usr->disable)
	   			continue;

	   		// file synchronization disabled
			if (!$usr->filesync)
			{
				$output->writeln('+++ User "'.$usr->firstname.' '.$usr->lastname.'" excluded from notification');
				continue;
			}

			// collect files
			$files = $usr_files[$usr->id];
			foreach (unserialize($usr->groups) as $grp)
			{
				foreach ($grp_files[$grp] as $k => $file)
				{
					if (is_numeric($k))
					{
						if (!($p = strrpos($file, '/')))
							continue;
						if (strpos(substr($file, $p + 1), '.'))
							$files[] = $file;
					}
				}
				// any file found?
				if (!count($files) || !isset($grp_files[$grp]['form']))
					continue;

	    		// send email
	   			$email = new Email();
	   			$email->subject($grp_files[$grp]['form']->subject);
	   			$email->from($grp_files[$grp]['form']->recipient);
	   			$txt   = '';
				foreach (FormFieldModel::findByPId($grp_files[$grp]['form']->id) as $field)
				{
					if ($field->type == 'explanation')
						$txt = $field->text;
				}
				$email->html(str_replace('[[files]]', implode('<br>', $files), $txt));
				$name = $usr->firstname.' '.$usr->lastname.' <'.$usr->email.'>';

				$email->to($name);
				$output->writeln('E-Mail notification send to "'.$name.'"');
				$mailer->send($email);
			}
	   }
    }

}
