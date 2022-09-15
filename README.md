# TableIntoGSheet

This script can be called directly from command line.
For parameters, see documentation comments at lines 39 and 74 ;)

```
php copy.php -iMyID -hlocalhost -dmydatabase -uroot -tMyTable -sMySheetPage
```

Can also append data to a new row

```
php copy.php -iMyID -a firstColValue secondColValue
```

# Usage

Store credentials of your service account in a file named `credentials.json`.

For guide about spreadsheet IDs and service accounts see ![this repository readme file](https://github.com/juampynr/google-spreadsheet-reader).

# Installation
```composer install```