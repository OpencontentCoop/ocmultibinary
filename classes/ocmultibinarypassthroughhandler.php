<?php

class OCMultiBinaryPassthroughHandler extends eZBinaryFileHandler
{
    const HANDLER_ID = 'ezfilepassthrough';

    function __construct()
    {
        parent::__construct(self::HANDLER_ID, "PHP passthrough", eZBinaryFileHandler::HANDLE_DOWNLOAD);
    }

    function handleFileDownload($contentObject, $contentObjectAttribute, $type,
                                $fileInfo)
    {
        $fileName = $fileInfo['filepath'];

        $file = eZClusterFileHandler::instance($fileName);

        if ($fileName != "" and $file->exists()) {
#            $file->fetch( true );
            if ($file instanceof eZDFSFileHandler) {
                $path = eZINI::instance('file.ini')->variable('eZDFSClusteringSettings', 'MountPointPath');
                $fileName = $path . '/' . $fileName;
            }
            $fileSize = $file->size();
            $mimeType = $fileInfo['mime_type'];
            $originalFileName = $fileInfo['original_filename'];
            $contentLength = $fileSize;
            $fileOffset = false;
            $fileLength = false;
            if (isset($_SERVER['HTTP_RANGE'])) {
                $httpRange = trim($_SERVER['HTTP_RANGE']);
                if (preg_match("/^bytes=(\d+)-(\d+)?$/", $httpRange, $matches)) {
                    $fileOffset = $matches[1];
                    if (isset($matches[2])) {
                        $fileLength = $matches[2] - $matches[1] + 1;
                        $lastPos = $matches[2];
                    } else {
                        $fileLength = $fileSize - $matches[1];
                        $lastPos = $fileSize - 1;
                    }
                    header("Content-Range: bytes $matches[1]-" . $lastPos . "/$fileSize");
                    header("HTTP/1.1 206 Partial Content");
                    $contentLength = $fileLength;
                }
            }
            // Figure out the time of last modification of the file right way to get the file mtime ... the
            $fileModificationTime = filemtime($fileName);

            ob_clean();
            header("Pragma: ");
            header("Cache-Control: ");
            /* Set cache time out to 10 minutes, this should be good enough to work around an IE bug */
            header("Expires: " . gmdate('D, d M Y H:i:s', time() + 600) . ' GMT');
            header("Last-Modified: " . gmdate('D, d M Y H:i:s', $fileModificationTime) . ' GMT');
            header("Content-Length: $contentLength");
            header("Content-Type: $mimeType");
            header("X-Powered-By: eZ Publish");

            $dispositionType = self::dispositionType($mimeType);
            header("Content-disposition: $dispositionType; filename=\"$originalFileName\"");

            header("Content-Transfer-Encoding: binary");
            header("Accept-Ranges: bytes");

            $fh = fopen("$fileName", "rb");
            if ($fileOffset !== false && $fileLength !== false) {
                echo stream_get_contents($fh, $contentLength, $fileOffset);
            } else {
                ob_end_clean();
                fpassthru($fh);
            }
            fclose($fh);

            eZExecution::cleanExit();
        }
        return eZBinaryFileHandler::RESULT_UNAVAILABLE;
    }

    /**
     * Checks if a file should be downloaded to disk or displayed inline in
     * the browser.
     *
     * This method returns "attachment" if no setting for the mime type is found.
     *
     * @param string $mimeType
     * @return string "attachment" or "inline"
     */
    protected static function dispositionType($mimeType)
    {
        $ini = eZINI::instance('file.ini');

        $mimeTypes = (array)$ini->variable('PassThroughSettings', 'ContentDisposition');
        if (isset($mimeTypes[$mimeType])) {
            return $mimeTypes[$mimeType];
        }

        return "attachment";
    }
}
