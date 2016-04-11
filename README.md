# OpenContent Multibinary Datatype ##

## Installazione

 1. installare tabella:  ```$ psql -h host -U user -d database -f ocmultibinary.sql ```

 2. attivare estensione

 3. rigenerare autoloads

 4. svuotare cache

## Conversione attributo ezbinary 

Per convertire un attributo ezbinary usare script:

```
$ php extension/ocmultibinary/bin/php/convert_from_ezbinary.php --class=CLASSE --attribute=ATTRIBUTO
```