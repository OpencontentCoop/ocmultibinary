<?php
/**
 * Test standalone (nessun framework, nessun kernel eZ Publish richiesto) per
 * OCMultiBinaryType::normalizeOriginalFilename(), looksEncoded() e decodeDisplayName().
 *
 * Un import massivo ha ri-codificato original_filename più volte con urlencode():
 * il test riproduce il danno applicando urlencode() k volte a nomi reali e verifica che
 * la normalizzazione torni sempre al singolo livello atteso.
 *
 * Esecuzione: php tests/ocmultibinary_normalize_filenames_test.php
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
    public $SuffixList = array('pdf' => true, 'md' => true);

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

function encode_times($name, $times)
{
    for ($i = 0; $i < $times; $i++) {
        $name = urlencode($name);
    }
    return $name;
}

function clean_file_name($filename)
{
    $ref = new ReflectionMethod('OCMultiBinaryType', 'cleanFileName');
    $ref->setAccessible(true);
    return $ref->invoke(null, $filename);
}

$names = [
    'Contratto decet + relazione tec + rel.illus anno 2023 + 2025.pdf',
    'Contratto decentrato integrativo anno 2022.pdf',
    'contratto collettivo dec. int. anno 2021 accordo 2021 e parte economica.pdf',
    'Contratto collettivo dec.integ.anno 2020, accordo 2020 e parte economica.pdf',
    'Contratto integrativo dirigenti 2002 - 2005 2° biennio economico.pdf',
    'Regolamento Edilizio - Allegato A (2).pdf',
    'Relazione_tecnica-finale.pdf',
    'senza spazi.pdf',
];

foreach ($names as $name) {
    $expected = urlencode($name);
    // A due livelli un nome con soli spazi/"+" è indistinguibile da un nome sano con "+" letterali
    // ("%2B" isolato): il livello 2 è verificabile solo se c'è un altro carattere codificato.
    $hasOtherEncodedChar = (bool)preg_match('/[^A-Za-z0-9 +._-]/', $name);
    $testedLevels = $hasOtherEncodedChar ? [1, 2, 3, 4, 5] : [1, 3, 4, 5];
    foreach ($testedLevels as $levels) {
        check(
            'livelli=' . $levels . ' torna al singolo livello: ' . $name,
            OCMultiBinaryType::normalizeOriginalFilename(encode_times($name, $levels)) === $expected
        );
    }
    check(
        'idempotente: ' . $name,
        OCMultiBinaryType::normalizeOriginalFilename($expected) === $expected
    );
    check(
        'display_name dal valore normalizzato uguale a quello del nome sano: ' . $name,
        clean_file_name(OCMultiBinaryType::normalizeOriginalFilename(encode_times($name, 3))) === clean_file_name($expected)
    );
}

// Esempio reale visto in produzione: spazio a tre livelli = %252B, "+" a tre livelli = %25252B
check(
    'esempio reale a tre livelli',
    OCMultiBinaryType::normalizeOriginalFilename(
        'Contratto%252Bdecet%252B%25252B%252Brelazione%252Btec.pdf'
    ) === 'Contratto+decet+%2B+relazione+tec.pdf'
);

// Un "%" letterale nel nome è già codificato a "%25" ma non seguito da una coppia esadecimale:
// non è corruzione e non va toccato
check(
    'percento letterale non toccato',
    OCMultiBinaryType::normalizeOriginalFilename(urlencode('sconto 100%.pdf')) === urlencode('sconto 100%.pdf')
);
check('stringa vuota', OCMultiBinaryType::normalizeOriginalFilename('') === '');

// display_name reali letti dall'API di produzione
check(
    'decodeDisplayName: tre livelli con spazi e "+"',
    OCMultiBinaryType::decodeDisplayName(
        'Contratto%252Bdecet%252B%25252B%252Brelazione%252Btec%252B%25252B%252Brel.illus%252Banno%252B2023%252B 2025'
    ) === 'Contratto decet relazione tec rel.illus anno 2023 2025'
);
check(
    'decodeDisplayName: soli spazi a tre livelli',
    OCMultiBinaryType::decodeDisplayName('Contratto%252Bdecentrato%252Bintegrativo%252Banno%252B2022')
        === 'Contratto decentrato integrativo anno 2022'
);
check(
    'decodeDisplayName: "+" letterali a un livello diventano spazi come in cleanFileName',
    OCMultiBinaryType::decodeDisplayName('Sentenza%2Bn%C2%B01 2020%2B %2Bdocumento%2Boriginale')
        === 'Sentenza n°1 2020 documento originale'
);
check(
    'decodeDisplayName: parentesi a un livello (%28 %29)',
    OCMultiBinaryType::decodeDisplayName('Manuale lavatrice candy %281%29') === 'Manuale lavatrice candy (1)'
);
check(
    'decodeDisplayName: un solo livello (%C2%B0)',
    OCMultiBinaryType::decodeDisplayName('Contratto integrativo 2006 2009 2%C2%B0 biennio economico')
        === 'Contratto integrativo 2006 2009 2° biennio economico'
);
check(
    'decodeDisplayName: display_name leggibile invariato',
    OCMultiBinaryType::decodeDisplayName('Contratto decentrato accordo integrativo 2024')
        === 'Contratto decentrato accordo integrativo 2024'
);
check(
    'decodeDisplayName: suffisso (2) di disambiguazione conservato',
    OCMultiBinaryType::decodeDisplayName('Relazione (2)') === 'Relazione (2)'
);
check('decodeDisplayName: null invariato', OCMultiBinaryType::decodeDisplayName(null) === null);
check('decodeDisplayName: stringa vuota', OCMultiBinaryType::decodeDisplayName('') === '');
check(
    'decodeDisplayName: idempotente',
    OCMultiBinaryType::decodeDisplayName(
        OCMultiBinaryType::decodeDisplayName('Contratto%252Bdecentrato%252B2022')
    ) === 'Contratto decentrato 2022'
);

check('looksEncoded: %2B',OCMultiBinaryType::looksEncoded('Contratto%2Bdecet') === true);
check('looksEncoded: %C2%B0', OCMultiBinaryType::looksEncoded('2%C2%B0 biennio') === true);
check('looksEncoded: nome pulito', OCMultiBinaryType::looksEncoded('Contratto decentrato 2024') === false);
check('looksEncoded: percento isolato', OCMultiBinaryType::looksEncoded('sconto 100%') === false);

echo $passed . " test passati, " . count($failures) . " falliti.\n";
if ($failures) {
    foreach ($failures as $f) {
        echo "  FALLITO: " . $f . "\n";
    }
    exit(1);
}
exit(0);
