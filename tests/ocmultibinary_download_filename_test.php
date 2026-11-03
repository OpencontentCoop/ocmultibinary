<?php
/**
 * Test standalone (nessun framework, nessun kernel eZ Publish richiesto) per
 * OCMultiBinaryType::matchStoredFileName().
 *
 * Il link di download è costruito dai template con il filename salvato senza urlencode(). Se il
 * filename contiene "?" (file importati da un link Google Drive: "<hash>.uc?id=...&export=download")
 * il web server spezza l'url lì e al modulo arriva solo "<hash>.uc": il lookup esatto non trova il
 * file e il download risponde 404. matchStoredFileName() ritrova il filename salvato.
 *
 * Esecuzione: php tests/ocmultibinary_download_filename_test.php
 */

class eZDataType
{
    function __construct($string, $name, $options = array())
    {
    }

    static function register($string, $class)
    {
    }
}

class eZMimeType
{
    public $SuffixList = array();

    private static $instance;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

require __DIR__ . '/../datatypes/ocmultibinary/ocmultibinarytype.php';

$failures = [];
$passed = 0;

function check($label, $condition)
{
    global $failures, $passed;
    if ($condition) {
        $passed++;
    } else {
        $failures[] = $label;
    }
}

$drive = '0123456789abcdef0123456789abcdef.uc?id=EXAMPLEDRIVEFILEID&export=download';
$other = 'fedcba9876543210fedcba9876543210.pdf';

// nome salvato richiesto per intero: invariato
check('nome esatto', OCMultiBinaryType::matchStoredFileName([$other, $drive], $other) === $other);
check('nome esatto con ? e &', OCMultiBinaryType::matchStoredFileName([$other, $drive], $drive) === $drive);

// url spezzato dal "?": si ritrova il nome salvato
check(
    'url spezzato al "?"',
    OCMultiBinaryType::matchStoredFileName([$other, $drive], '0123456789abcdef0123456789abcdef.uc') === $drive
);
check(
    'url spezzato, unico allegato',
    OCMultiBinaryType::matchStoredFileName([$drive], '0123456789abcdef0123456789abcdef.uc') === $drive
);

// la corrispondenza esatta ha la precedenza
$plain = '0123456789abcdef0123456789abcdef.uc';
check(
    'esatto ha la precedenza sul prefisso',
    OCMultiBinaryType::matchStoredFileName([$drive, $plain], $plain) === $plain
);

// ambiguo (due nomi con lo stesso prefisso): non si indovina
$driveBis = '0123456789abcdef0123456789abcdef.uc?id=ALTRO&export=download';
check(
    'ambiguo: invariato',
    OCMultiBinaryType::matchStoredFileName([$drive, $driveBis], '0123456789abcdef0123456789abcdef.uc')
    === '0123456789abcdef0123456789abcdef.uc'
);

// nessuna corrispondenza: invariato
check('nessun allegato', OCMultiBinaryType::matchStoredFileName([], 'abc.uc') === 'abc.uc');
check('hash diverso', OCMultiBinaryType::matchStoredFileName([$drive], 'ffffffffffffffffffffffffffffffff.uc') === 'ffffffffffffffffffffffffffffffff.uc');

// un prefisso senza "?" non basta: "abc" non deve trovare "abcdef.pdf" né "abc.pdf?x"
check('prefisso parziale di un nome sano', OCMultiBinaryType::matchStoredFileName(['abcdef.pdf'], 'abc') === 'abc');
check('prefisso senza separatore', OCMultiBinaryType::matchStoredFileName(['abc.uc2?x=1'], 'abc.uc') === 'abc.uc');

// valori limite
check('id vuoto', OCMultiBinaryType::matchStoredFileName([$drive], '') === '');
check('id non stringa', OCMultiBinaryType::matchStoredFileName([$drive], null) === null);
check('elenco con valori non stringa', OCMultiBinaryType::matchStoredFileName([null, $drive], '0123456789abcdef0123456789abcdef.uc') === $drive);

if ($failures) {
    echo "FALLITI (" . count($failures) . "):\n - " . implode("\n - ", $failures) . "\n";
    echo $passed . " test passati.\n";
    exit(1);
}
echo $passed . " test passati, 0 falliti.\n";
