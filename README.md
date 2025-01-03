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
Search for totep42/filesync bundle and add it to your extensions.
```

After installing the contao-member-extension-bundle, you need to run a **contao install**.


## Usage

1. Define `Member groups` exactly as the folder names were defined (e.g. member group `Group1`). 
2. Assign this group to a member.
4. Create a directory below `files` directory named like your group (e.g. `files/Group1`). 
5. Then copy any files to the directory and call `vendor/contao-console toteph42:filesync`. 

#### Disable notification

1. Uncheck checkbox `Notify user about changes in file system` in member definition (default=true).

#### Member configuration 

If you want members to give the possibility to select whether they want notification or not in frontend:

1. Create a frontend module `Personal data`.
2. Select field `Notify user about changes in file system` as `Editable fields`.
3. Create an `Article` and include the module you just created.

#### Creating message to send

Now you need to create a `Forms` with the name of the group (e.g. member group `Group1`).

1. Click on checkbox `Send form data via e-mail` in section **Send form data**.
2. Enter the `sender` address in field `Recipient address` (e.g.**from@exaple.com**).
3. Add your `Subject`.
4. Select **Data format** `E-mail`.
5. **Save and close**
6. Add a content field with **Field type** `Explanation`.
7. Enter your e-Mail in `Text` field. Use the placehoder `[[files]]` where you want the list of files to be inserted.
8. **Save and close**

#### Testing

To test, please go to your web directory and use the command

```
/vendor/bin/contao-console toteph42:filesync
```

### Production

To start sending notification e-Mails, you need to edit your crontab using `crontab -e` and enter:

```
# Synchronize files
30 * * * *    /opt/php8.3.13/bin/php [Path to your Contao installation]/vendor/bin/contao-console toteph42:filesync
```

Please enjoy!

If you enjoy my software, I would be happy to receive a donation.

<a href="https://www.paypal.com/donate/?hosted_button_id=DS6VK49NAFHEQ" target="_blank" rel="noopener">
  <img src="https://www.paypalobjects.com/en_US/DK/i/btn/btn_donateCC_LG.gif" alt="Donate with PayPal"/>
</a>

