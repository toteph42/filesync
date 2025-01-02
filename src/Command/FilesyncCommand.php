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
        $this->framework->initialize();

        $output->writeln('Synchronizing…');

        $time = microtime(true);
        $changeSet = $this->dbafsManager->sync(...$input->getArgument('paths'));
        $timeTotal = round(microtime(true) - $time, 2);

        $this->renderStats($changeSet, $output);

        (new SymfonyStyle($input, $output))->success("Synchronization complete in {$timeTotal}s.");

        return Command::FAILURE;
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

        $output->writeln('Checking for file notification…');

        // get all group names
    	$groups = [];
    	foreach (MemberGroupModel::findAll() as $item)
    		$groups[$item->id] = $item->name;

    	$users = MemberModel::findAll();

    	// get all files from user
    	$files = self::getfiles($groups, $users, $changeSet->getItemsToCreate()) +
    			 self::getfiles($groups, $users, $changeSet->getItemsToUpdate());

		// get transport
		$transport = Transport::fromDsn($_SERVER['MAILER_DSN']);
		$mailer = new Mailer($transport);

		// check files
    	foreach ($files as $grp => $file)
    	{
			// try to find form for group
    		if (!($form = FormModel::findByTitle($groups[$grp])) || $form->format != 'email')
    		{
    			$output->writeln('+++ No form for "'.$groups[$grp].'" found - skipping');
   				continue;
    		}

    		// collect all user of this group
    		$to = [];
    		foreach ($users as $usr)
    		{
				if (strpos($usr->groups, '"'.$grp.'"'))
				{
					if ($usr->filesync == 0)
						$output->writeln('+++ User "'.$usr->firstname.' '.$usr->lastname.'" excluded from notification');
					else
						$to[] = $usr->firstname.' '.$usr->lastname.' <'.$usr->email.'>';
				}
    		}

    		// send email
   			$email = new Email();
   			$email->subject($form->subject);
   			$email->from($form->recipient);
   			$txt   = '';
			foreach (FormFieldModel::findByPId($form->id) as $field)
			{
				if ($field->type == 'explanation')
					$txt = $field->text;
			}
			$email->html(str_replace('[[files]]', isset($files[$grp]) ? implode('<br>', $files[$grp]) : '', $txt));

			// send mail
 			foreach ($to as $name)
			{
				$email->to($name);
				$output->writeln('E-Mail notification send to "'.$name.'"');
				$mailer->send($email);
			}
    	}
    	$file; // disable Eclipse warning


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

    private function getfiles(array $groups, Collection &$users, array $items): array
    {
    	$out = [];

    	// process list of files
    	foreach ($items as $item)
		{
			$path = strpos(get_class($item), 'ItemToCreate') ? $item->getPath() : $item->getExistingPath();

			foreach ($users as $usr)
    		{
    			foreach (unserialize($usr->groups) as $grp)
    			{
    				if (($p = strpos($path, $groups[$grp])) === false)
    					continue;
    				$file = substr($path, $p + strlen($groups[$grp]));
					if (strpos($file, '.'))
					{
						if (!isset($out[$grp]))
							$out[$grp] = [];
						$out[$grp][] = $file;
					}
    			}
			}
		}

		return $out;
    }

}
