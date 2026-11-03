<?php
/**
 * Riporta al singolo livello di urlencode() gli original_filename ri-codificati più volte (es.
 * "%252B" al posto di "+") e ricalcola i display_name rimasti percent-encoded.
 *
 * Tocca, per tutte le versioni degli attributi ocmultibinary del siteaccess:
 * - ezbinaryfile.original_filename
 * - le decorations serializzate in ezcontentobject_attribute.data_text
 *   (original_filename e, se percent-encoded, display_name)
 *
 * Di default è un DRY-RUN di sola lettura. Con --apply scrive, ma solo se è indicato --backup-dir:
 * prima salva i valori originali in JSON, poi aggiorna in un'unica transazione.
 *
 * Uso (dalla root di eZ Publish):
 *   php extension/ocmultibinary/bin/php/normalize_attachment_filenames.php -s <siteaccess>
 *   php extension/ocmultibinary/bin/php/normalize_attachment_filenames.php -s <siteaccess> --apply --backup-dir=/percorso/scrivibile
 */
require 'autoload.php';

$script = eZScript::instance(array(
    'description' => "Normalizza gli original_filename degli allegati ocmultibinary ri-codificati più volte",
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
));

$script->startup();

$options = $script->getOptions(
    '[apply][backup-dir:][samples:]',
    '',
    array(
        'apply' => 'Scrive le modifiche (richiede --backup-dir). Senza questa opzione è un dry-run',
        'backup-dir' => 'Cartella scrivibile dove salvare i valori originali prima di scrivere',
        'samples' => "Quanti esempi prima/dopo stampare (default 15)",
    )
);
$script->initialize();

$cli = eZCLI::instance();
$db = eZDB::instance();
$apply = (bool)$options['apply'];
$backupDir = $options['backup-dir'];
$samples = $options['samples'] !== null ? (int)$options['samples'] : 15;

if ($apply) {
    if (!$backupDir || !is_dir($backupDir) || !is_writable($backupDir)) {
        $cli->error('--apply richiede --backup-dir=<cartella esistente e scrivibile>');
        $script->shutdown(1);
    }
}

$cli->output($apply ? 'MODALITA: APPLY' : 'MODALITA: DRY-RUN (nessuna scrittura)');

// ---------------------------------------------------------------------------------------------
// 1. ezbinaryfile.original_filename
// ---------------------------------------------------------------------------------------------
$binaryRows = $db->arrayQuery(
    "SELECT b.contentobject_attribute_id AS attribute_id, b.version, b.filename, b.original_filename
     FROM ezbinaryfile b
     JOIN ezcontentobject_attribute a ON a.id = b.contentobject_attribute_id AND a.version = b.version
     WHERE a.data_type_string = '" . $db->escapeString(OCMultiBinaryType::DATA_TYPE_STRING) . "'"
);

$binaryChanges = [];
$binaryAmbiguous = 0;
$levelHistogram = [];
$binaryOriginalsByAttribute = [];
foreach ($binaryRows as $row) {
    $old = $row['original_filename'];
    $new = OCMultiBinaryType::normalizeOriginalFilename($old);
    $key = $row['attribute_id'] . ':' . $row['version'];
    $binaryOriginalsByAttribute[$key]['old'][$old] = true;
    $binaryOriginalsByAttribute[$key]['new'][$new] = true;
    if ($new !== $old) {
        $levels = OCMultiBinaryType::estimateEncodingLevels($old);
        $levelHistogram[$levels] = ($levelHistogram[$levels] ?? 0) + 1;
        $binaryChanges[] = [
            'attribute_id' => (int)$row['attribute_id'],
            'version' => (int)$row['version'],
            'filename' => $row['filename'],
            'old' => $old,
            'new' => $new,
        ];
    } elseif (strpos($old, '%2B') !== false || strpos($old, '%2b') !== false) {
        // "%2B" isolato: "+" letterale oppure spazio a due livelli, non si può sapere: non si tocca
        $binaryAmbiguous++;
    }
}

// ---------------------------------------------------------------------------------------------
// 2. decorations serializzate in ezcontentobject_attribute.data_text
// ---------------------------------------------------------------------------------------------
$attributeRows = $db->arrayQuery(
    "SELECT id, version, contentobject_id, data_text
     FROM ezcontentobject_attribute
     WHERE data_type_string = '" . $db->escapeString(OCMultiBinaryType::DATA_TYPE_STRING) . "'
       AND data_text IS NOT NULL AND data_text <> ''"
);

$attributeChanges = [];
$decorationOrphansBefore = 0;
$decorationOrphansAfter = 0;
$displayNameRewritten = 0;
$displayNameDuplicates = 0;
$unserializeFailures = 0;
$displaySamples = [];
foreach ($attributeRows as $row) {
    $stored = @unserialize($row['data_text'], ['allowed_classes' => false]);
    if (!is_array($stored)) {
        $unserializeFailures++;
        continue;
    }

    $changed = false;
    $usedNamesBefore = [];
    $usedNamesAfter = [];
    $newStored = [];
    foreach ($stored as $index => $decoration) {
        if (!is_array($decoration)) {
            // formato legacy: la sola stringa original_filename
            $new = OCMultiBinaryType::normalizeOriginalFilename($decoration);
            if ($new !== $decoration) {
                $changed = true;
            }
            $newStored[$index] = $new;
            continue;
        }

        $oldOriginal = $decoration['original_filename'] ?? '';
        $newOriginal = OCMultiBinaryType::normalizeOriginalFilename($oldOriginal);
        $newDecoration = $decoration;
        if ($newOriginal !== $oldOriginal) {
            $newDecoration['original_filename'] = $newOriginal;
            $changed = true;
        }

        $oldDisplay = $decoration['display_name'] ?? '';
        if (OCMultiBinaryType::looksEncoded($oldDisplay)) {
            $newDisplay = OCMultiBinaryType::decodeDisplayName($oldDisplay);
            if ($newDisplay !== $oldDisplay) {
                $newDecoration['display_name'] = $newDisplay;
                $changed = true;
                $displayNameRewritten++;
                if (count($displaySamples) < $samples) {
                    $displaySamples[] = [$oldDisplay, $newDisplay];
                }
            }
        }

        // conta solo i duplicati introdotti dalla pulizia: due nomi diversi che dopo la
        // decodifica coincidono, non quelli già presenti nei dati
        $displayBefore = $oldDisplay;
        $displayAfter = $newDecoration['display_name'] ?? '';
        if ($displayAfter !== '' && isset($usedNamesAfter[$displayAfter])
            && !($displayBefore !== '' && isset($usedNamesBefore[$displayBefore]))
        ) {
            $displayNameDuplicates++;
        }
        $usedNamesBefore[$displayBefore] = true;
        $usedNamesAfter[$displayAfter] = true;
        $newStored[$index] = $newDecoration;
    }

    // le decorations si collegano ai file per original_filename: dopo la pulizia non deve
    // restarne di orfane che prima non lo erano
    $key = $row['id'] . ':' . $row['version'];
    if (isset($binaryOriginalsByAttribute[$key])) {
        foreach ($stored as $index => $decoration) {
            $oldOriginal = is_array($decoration) ? ($decoration['original_filename'] ?? '') : $decoration;
            $newDec = $newStored[$index];
            $newOriginal = is_array($newDec) ? ($newDec['original_filename'] ?? '') : $newDec;
            if (!isset($binaryOriginalsByAttribute[$key]['old'][$oldOriginal])) {
                $decorationOrphansBefore++;
            }
            if (!isset($binaryOriginalsByAttribute[$key]['new'][$newOriginal])) {
                $decorationOrphansAfter++;
            }
        }
    }

    if ($changed) {
        $attributeChanges[] = [
            'id' => (int)$row['id'],
            'version' => (int)$row['version'],
            'old_data_text' => $row['data_text'],
            'new_data_text' => serialize($newStored),
        ];
    }
}

// ---------------------------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------------------------
$cli->output('');
$cli->output('ezbinaryfile: righe totali ' . count($binaryRows) . ', da correggere ' . count($binaryChanges));
ksort($levelHistogram);
foreach ($levelHistogram as $levels => $count) {
    $cli->output('  livelli di codifica stimati = ' . $levels . ': ' . $count);
}
$cli->output('  "%2B" isolati lasciati invariati (ambigui): ' . $binaryAmbiguous);
$cli->output('decorations: attributi/versioni totali ' . count($attributeRows) . ', da correggere ' . count($attributeChanges));
$cli->output('  display_name riscritti: ' . $displayNameRewritten);
$cli->output('  display_name resi duplicati dalla pulizia nello stesso attributo: ' . $displayNameDuplicates);
$cli->output('  decorations non deserializzabili (saltate): ' . $unserializeFailures);
$cli->output('  decorations senza file corrispondente: prima ' . $decorationOrphansBefore . ', dopo ' . $decorationOrphansAfter);
if ($decorationOrphansAfter > $decorationOrphansBefore) {
    $cli->error('ATTENZIONE: la pulizia creerebbe decorations orfane in più. Non applicare.');
}

$cli->output('');
$cli->output('Esempi original_filename (prima -> dopo):');
foreach (array_slice($binaryChanges, 0, $samples) as $change) {
    $cli->output('  ' . $change['attribute_id'] . '/v' . $change['version'] . ' ' . $change['old']);
    $cli->output('      -> ' . $change['new']);
}
$cli->output('Esempi display_name (prima -> dopo):');
foreach ($displaySamples as $pair) {
    $cli->output('  ' . $pair[0]);
    $cli->output('      -> ' . $pair[1]);
}

if (!$apply) {
    $cli->output('');
    $cli->output('Dry-run: nessuna scrittura. Per applicare: --apply --backup-dir=<cartella>');
    $script->shutdown();
    return;
}

if ($decorationOrphansAfter > $decorationOrphansBefore) {
    $script->shutdown(1);
    return;
}

// ---------------------------------------------------------------------------------------------
// Apply: backup, poi transazione
// ---------------------------------------------------------------------------------------------
$stamp = date('Ymd-His');
$binaryBackup = rtrim($backupDir, '/') . '/ocmultibinary-binary-' . $stamp . '.json';
$attributeBackup = rtrim($backupDir, '/') . '/ocmultibinary-attributes-' . $stamp . '.json';
if (
    file_put_contents($binaryBackup, json_encode($binaryChanges, JSON_UNESCAPED_UNICODE)) === false
    || file_put_contents($attributeBackup, json_encode($attributeChanges, JSON_UNESCAPED_UNICODE)) === false
) {
    $cli->error('Backup non scritto: nessuna modifica applicata');
    $script->shutdown(1);
    return;
}
$cli->output('Backup scritto: ' . $binaryBackup . ' e ' . $attributeBackup);

$db->begin();
foreach ($binaryChanges as $change) {
    $db->query(
        "UPDATE ezbinaryfile SET original_filename = '" . $db->escapeString($change['new']) . "'
         WHERE contentobject_attribute_id = " . (int)$change['attribute_id'] . "
           AND version = " . (int)$change['version'] . "
           AND filename = '" . $db->escapeString($change['filename']) . "'
           AND original_filename = '" . $db->escapeString($change['old']) . "'"
    );
}
foreach ($attributeChanges as $change) {
    $db->query(
        "UPDATE ezcontentobject_attribute SET data_text = '" . $db->escapeString($change['new_data_text']) . "'
         WHERE id = " . (int)$change['id'] . "
           AND version = " . (int)$change['version'] . "
           AND data_type_string = '" . $db->escapeString(OCMultiBinaryType::DATA_TYPE_STRING) . "'"
    );
}
$db->commit();

$cli->output('Applicato: ' . count($binaryChanges) . ' righe ezbinaryfile, ' . count($attributeChanges) . ' attributi con decorations.');
$cli->output('Ricordati di svuotare la cache del tenant (clear_cache.yml) per vedere le modifiche sul sito.');

$script->shutdown();
