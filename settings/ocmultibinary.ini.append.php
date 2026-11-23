<?php /* #?ini charset="utf-8"?

[Settings]
Runtimes=gears,flash,silverlight,browserplus

[SearchSettings]
UseMetaDataExtractor=disabled

[AcceptFileTypesRegex]
#ClassAttributeIdentifier[class_identifier/attribute_identifier]=/\.(gif|jpe?g|png)$/i
ClassAttributeIdentifier[]

[StorageSettings]
# When enabled (default), the physical file is deleted from the cluster as soon as no ezbinaryfile row references it
# any more (version removed, draft discarded, translation removed, object purged). The cluster delete is logical:
# the file is physically removed later by bin/php/clusterpurge.php, after its --expiry (default 30 days).
# Set to disabled to keep the files: every deletion is then only written to var/<instance>/log/ocmultibinary-delete.log
# as "skipped". Can be set per tenant with EZINI_ocmultibinary__StorageSettings__DeleteUnreferencedFiles=disabled
DeleteUnreferencedFiles=enabled

[NameConflictSettings]
# When enabled, uploading a file whose name matches an attachment already
# present in the same field shows a confirmation box (replace / keep both)
# before the upload starts, instead of silently replacing it.
EnableUploadConflictCheck=enabled
*/ ?>
