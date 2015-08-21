What is it
===========
It is a skeleton app to upload/download via ftp xls/xlsx files and adding the to archive with Gzip compression. Utilized time ~2h. Need to be finished...

How to install
=======

Windows/Linux
-----
Clone git or download by https://codeload.github.com/cryomi/skysiss/zip/master 

Open cmd and 'cd' to git project

```php
php -r "readfile('https://getcomposer.org/installer');" | php
```

Run composer

```php
php composer.phar update
```

Run
```php
php test.php test.xlsx
```
It will create archive in project folder and parse xlsx file with memory stats

Uncomment FTP section and replace with user/pass to test ftp
