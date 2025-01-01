## Synconize files and send e-Mail notification to members for Contao Open Source CMS ##

![](https://img.shields.io/packagist/v/toteph42/filesync.svg)
![](https://img.shields.io/packagist/l/toteph42/filesync.svg)
![](https://img.shields.io/packagist/dt/toteph42/filesync.svg)

This small extension replaces to Contao internal `contao:filesync` command by `toteph42:filesync`.
On top of the file synchronization, e-Mail notifications were send to users if any file has changed or is new.

## Installation

#### Via composer
```
composer require toteph42/filesync
```

#### Via contao-manager
```
Search for filesyncbundle and add it to your extensions.
```

After installing the contao-member-extension-bundle, you need to run a **contao install**.


## Usage

1. Define `MemberGroups` exactly as the folder names were defined (e.g. member group `Group1`). 
2. Assign this group to a member.
4. Create a directory below `files` directory named like your group (e.g. `Group1`). 
5. Then copy any files to the directory and call `vendor/contao-console toteph42:filesync`. 

If you want members to disable notification:

1. Uncheck checkbox `Notify user about changes in file system` in member definition (default=true).

If you want members to give the possibility to select wheter they want notification or not in frontend:

1. Create a frontend module `Personaldata`.
2. Select field `Filesync`as editable field.
3. Create a page with the frontend module included.

Please enjoy!

If you enjoy my software, I would be happy to receive a donation.

<a href="https://www.paypal.com/donate/?hosted_button_id=DS6VK49NAFHEQ" target="_blank" rel="noopener">
  <img src="https://www.paypalobjects.com/en_US/DK/i/btn/btn_donateCC_LG.gif" alt="Donate with PayPal"/>
</a>

