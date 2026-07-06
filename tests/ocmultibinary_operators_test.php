<?php
/**
 * Test standalone (nessun framework, nessun kernel eZ Publish richiesto) per
 * OCMultiBinaryOperators::modify(). Copre il bug per cui un file senza una
 * decorazione salvata (display_order/display_group non inizializzati, quindi
 * null) faceva sparire altri file nel rendering frontend, e verifica che il
 * comportamento corretto pre-esistente non sia cambiato.
 *
 * Esecuzione: php tests/ocmultibinary_operators_test.php
 */

// Stub minimo: basta l'interfaccia usata dagli operatori (attribute/setAttribute),
// non serve estendere la vera eZMultiBinaryFile (che richiede il kernel/DB).
class StubMultiBinaryFile
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function attribute($name)
    {
        return array_key_exists($name, $this->data) ? $this->data[$name] : null;
    }

    public function setAttribute($name, $value)
    {
        $this->data[$name] = $value;
    }
}

// ocmultibinary_available_groups fa `instanceof eZContentObjectAttribute`.
// La vera classe non è caricata (nessun kernel eZ Publish in questo test), quindi
// definiamo uno stub omonimo solo per soddisfare il type-check.
class eZContentObjectAttribute
{
    private $files;

    public function __construct(array $files)
    {
        $this->files = $files;
    }

    public function content()
    {
        return $this->files;
    }
}

require __DIR__ . '/../autoloads/ocmultibinaryoperators.php';

$failures = [];
$passed = 0;

function run_operator($operatorName, $namedParameters)
{
    $ops = new OCMultiBinaryOperators();
    $tpl = null;
    $operatorParameters = [];
    $rootNamespace = null;
    $currentNamespace = null;
    $operatorValue = null;
    $ops->modify($tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace, $operatorValue, $namedParameters);
    return $operatorValue;
}

function check($label, $condition)
{
    global $failures, $passed;
    if ($condition) {
        $passed++;
    } else {
        $failures[] = $label;
    }
}

function filenames($fileList)
{
    // array_values: a noi interessa l'ordine dei valori, non le chiavi
    // dell'array intermedio (il codice originale, non corretto, usa
    // display_order come chiave e quindi non reindicizza da 0).
    return array_values(array_map(function ($f) {
        return $f->attribute('original_filename');
    }, $fileList));
}

// --- Caso base: decorazioni tutte presenti, ordine e gruppo espliciti ---
// Nessuna regressione attesa: stesso identico comportamento di prima del fix.
$a = new StubMultiBinaryFile(['original_filename' => 'a.pdf', 'display_group' => '', 'display_order' => 2]);
$b = new StubMultiBinaryFile(['original_filename' => 'b.pdf', 'display_group' => '', 'display_order' => 1]);
$c = new StubMultiBinaryFile(['original_filename' => 'c.pdf', 'display_group' => 'Bandi', 'display_order' => 1]);
$attribute = new eZContentObjectAttribute([$a, $b, $c]);

$result = run_operator('ocmultibinary_list_by_group', ['attribute' => $attribute, 'group' => '']);
check('caso base: entrambi i file del gruppo vuoto presenti', filenames($result) === ['b.pdf', 'a.pdf']);

$result = run_operator('ocmultibinary_list_by_group', ['attribute' => $attribute, 'group' => 'Bandi']);
check('caso base: il gruppo "Bandi" non include file di altri gruppi', filenames($result) === ['c.pdf']);

// --- Caso del bug: un file senza decorazione salvata (display_order/display_group = null) ---
// Prima del fix: il file "orfano" (order=null) collassava sulla stessa chiave
// array di qualunque altro file con order=null, facendone sparire uno.
// Qui simuliamo 2 file orfani insieme a uno regolare: TUTTI devono restare.
$orphan1 = new StubMultiBinaryFile(['original_filename' => 'orphan1.pdf', 'display_group' => null, 'display_order' => null]);
$orphan2 = new StubMultiBinaryFile(['original_filename' => 'orphan2.pdf', 'display_group' => null, 'display_order' => null]);
$decorated = new StubMultiBinaryFile(['original_filename' => 'decorated.pdf', 'display_group' => '', 'display_order' => 1]);
$attribute = new eZContentObjectAttribute([$decorated, $orphan1, $orphan2]);

$result = run_operator('ocmultibinary_list_by_group', ['attribute' => $attribute, 'group' => '']);
check(
    'regressione bug: nessun file orfano scompare (tutti e 3 presenti)',
    count($result) === 3
);
check(
    'regressione bug: il file decorato resta primo, gli orfani in coda',
    filenames($result)[0] === 'decorated.pdf'
);
check(
    'regressione bug: entrambi gli orfani presenti indipendentemente dall\'ordine di elaborazione',
    in_array('orphan1.pdf', filenames($result)) && in_array('orphan2.pdf', filenames($result))
);

// --- Edge case correlato: due file con lo stesso display_order impostato manualmente ---
// (es. redattore digita "1" nel campo Sort di due file diversi). Anche qui,
// usare l'ordine come chiave d'array farebbe sparire uno dei due.
$dup1 = new StubMultiBinaryFile(['original_filename' => 'dup1.pdf', 'display_group' => '', 'display_order' => 5]);
$dup2 = new StubMultiBinaryFile(['original_filename' => 'dup2.pdf', 'display_group' => '', 'display_order' => 5]);
$attribute = new eZContentObjectAttribute([$dup1, $dup2]);

$result = run_operator('ocmultibinary_list_by_group', ['attribute' => $attribute, 'group' => '']);
check('edge case: due file con stesso display_order restano entrambi visibili', count($result) === 2);

// --- ocmultibinary_available_groups: un file orfano (display_group=null) non deve
// rompere il riordino "gruppo senza nome per ultimo" ---
$grouped = new StubMultiBinaryFile(['original_filename' => 'g.pdf', 'display_group' => 'Bandi']);
$orphan = new StubMultiBinaryFile(['original_filename' => 'o.pdf', 'display_group' => null]);
$attribute = new eZContentObjectAttribute([$grouped, $orphan]);

$result = run_operator('ocmultibinary_available_groups', ['attribute' => $attribute]);
check(
    'available_groups: il gruppo "senza nome" (anche se orfano/null) va in coda',
    $result === ['Bandi', '']
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
