Import Drupal users from TSV file.
------

You can find the template here:
https://docs.google.com/spreadsheets/d/1SKV9xTHPZK-KJrGUr8wHlfySheTB67csyMYMMYciJXs

User images should use the format name.extension (ex: username.jpg) without the path

The referenced files should be placed inside the folder /modules/custom/users_import/users_img/

Export the tsv and place it on your project

drush oai_import:users --file=/folder-where-your-file-is/users.tsv

