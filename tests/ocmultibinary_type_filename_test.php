<?php
/**
 * Test standalone (nessun framework, nessun kernel eZ Publish richiesto) per
 * OCMultiBinaryType::cleanFileName() e la generazione di display_name univoci
 * in OCMultiBinaryType::addFileToDecorations().
 *
 * Copre due bug:
 * - original_filename è salvato con urlencode() (spazi -> "+", parentesi ->
 *   "%28"/"%29"): cleanFileName() non lo decodificava prima di ripulirlo,
 *   producendo titoli con caratteri codificati ancora visibili.
 * - cleanFileName() rimuove sempre i suffissi "(N)": due file "keep both" con
 *   lo stesso nome finivano con lo stesso display_name, generando ambiguità
 *   nell'elenco allegati.
 *
 * Esecuzione: php tests/ocmultibinary_type_filename_test.php
 */

// Stub minimi: bastano per soddisfare i type-check/riferimenti di classe usati
// da OCMultiBinaryType a livello di dichiarazione, non serve il vero kernel.
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
    public $SuffixList = array(
        'md' => true,
        'pdf' => true,
        'jpg' => true,
    );

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

function call_private_static($method, array $args)
{
    $ref = new ReflectionMethod('OCMultiBinaryType', $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs(null, $args);
}

function clean_file_name($filename)
{
    return call_private_static('cleanFileName', [$filename]);
}

function unique_display_name($baseName, array $usedNames)
{
    return call_private_static('uniqueDisplayName', [$baseName, $usedNames]);
}

function find_matching_decoration_key($filename, $originalFilename, array $decorations, array $consumedKeys)
{
    return call_private_static('findMatchingDecorationKey', [$filename, $originalFilename, $decorations, $consumedKeys]);
}

// --- Caso base: nessuna regressione sul comportamento pre-esistente ---
check(
    'caso base: underscore e trattini diventano spazi, estensione nota rimossa',
    clean_file_name('my_file-name_report.pdf') === 'My file name report'
);

check(
    'caso base: i suffissi "(N)" restano rimossi come prima',
    clean_file_name('report(2).pdf') === 'Report'
);

// --- Bug 1: original_filename urlencoded deve essere decodificato prima della pulizia ---
check(
    'urlencoded: spazi codificati con "+" tornano spazi, non restano "+"',
    clean_file_name('docs-accessibilita-renamed+%281%29.md') === 'Docs accessibilita renamed'
);

check(
    'urlencoded: parentesi percent-encoded non compaiono più nel titolo',
    strpos(clean_file_name('report+%289%29.pdf'), '%28') === false
    && strpos(clean_file_name('report+%289%29.pdf'), '%29') === false
);

// --- Bug 2: display_name deve restare univoco quando più file condividono lo stesso nome pulito ---
check(
    'uniqueDisplayName: nessuna collisione, nome invariato',
    unique_display_name('Report', ['Altro file']) === 'Report'
);

check(
    'uniqueDisplayName: prima collisione -> suffisso "(2)"',
    unique_display_name('Report', ['Report']) === 'Report (2)'
);

check(
    'uniqueDisplayName: collisioni multiple -> primo suffisso libero',
    unique_display_name('Report', ['Report', 'Report (2)']) === 'Report (3)'
);

// --- findMatchingDecorationKey: il vero cuore del fix su cancellazione/riordino/edit ---

check(
    'nessuna decorazione -> nessun match',
    find_matching_decoration_key('phys-a.md', 'report.md', [], []) === null
);

check(
    'match per filename fisico (priorita\' sulla corrispondenza per original_filename)',
    find_matching_decoration_key(
        'phys-b.md',
        'report.md',
        [
            ['filename' => 'phys-a.md', 'original_filename' => 'report.md'],
            ['filename' => 'phys-b.md', 'original_filename' => 'report.md'],
        ],
        []
    ) === 1
);

check(
    'decorazione legacy senza filename -> match per original_filename',
    find_matching_decoration_key(
        'phys-a.md',
        'report.md',
        [['original_filename' => 'report.md']],
        []
    ) === 0
);

check(
    'due file con lo stesso original_filename ("keep both"): la chiave gia\' consumata viene saltata',
    find_matching_decoration_key(
        'phys-b.md',
        'report.md',
        [
            ['filename' => 'phys-a.md', 'original_filename' => 'report.md'],
            ['filename' => 'phys-b.md', 'original_filename' => 'report.md'],
        ],
        [0 => true]
    ) === 1
);

check(
    'nessuna decorazione libera corrispondente -> null (non deve inventare un match sbagliato)',
    find_matching_decoration_key(
        'phys-c.md',
        'report.md',
        [
            ['filename' => 'phys-a.md', 'original_filename' => 'report.md'],
            ['filename' => 'phys-b.md', 'original_filename' => 'report.md'],
        ],
        [0 => true, 1 => true]
    ) === null
);

// --- Riepilogo ---
echo $passed . " test passati, " . count($failures) . " falliti.\n";
if ($failures) {
    foreach ($failures as $f) {
        echo "  FALLITO: " . $f . "\n";
    }
    exit(1);
}
exit(0);
