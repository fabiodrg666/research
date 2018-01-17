The OAI module is a way to import repos using D-Space via OAI.
There's no interface for this module.

How to use?
-----------

Use drush:
drush oai-import:import --url="url"

An alias 'oai' is available. Example:

drush oai:import --url="https://repositorium.sdum.uminho.pt/oai/openaire?verb=ListRecords&metadataPrefix=oai_dc&set=com_1822_428"


Extras
------

The module also contains a way to import a tsv file. You can find the template here:
https://docs.google.com/spreadsheets/d/1SKV9xTHPZK-KJrGUr8wHlfySheTB67csyMYMMYciJXs

Export the file to a folder

drush oai_import:users --file=/folder-where-your-file-is/users.tsv
