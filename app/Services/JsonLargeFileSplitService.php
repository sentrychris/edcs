<?php

namespace App\Services;

class JsonLargeFileSplitService
{
    /**
     * Split a JSON file into parts for parallel processing.
     */
    public function split(string $filename, string $filepath, int $parts): void
    {
        // Calculate the number of JSON objects
        $objectsInFile = 0;
        $handle = fopen($filepath, 'r');
        if ($handle) {
            // Read each line and count JSON objects
            while (($line = fgets($handle)) !== false) {
                // Trim and skip empty lines and brackets
                $trimmedLine = trim($line);
                if ($trimmedLine === '' || $trimmedLine === '[' || $trimmedLine === ']') {
                    continue;
                }
                $objectsInFile++;
            }
            fclose($handle);
        }

        // Determine the number of objects per file to create equal parts
        $objectsPerFile = ceil($objectsInFile / $parts);

        $this->doSplit($filename, $filepath, $objectsPerFile);
    }

    /**
     * Split a JSON file into parts based on the number of objects per file.
     */
    private function doSplit(string $filename, string $filepath, int $objectsPerFile): void
    {
        $file = fopen($filepath, 'r');
        $part = 0;
        $currentObjectCount = 0;
        $outputFile = null;
        $outputFilePath = '';

        // Remove the opening bracket from the first line
        $firstLine = fgets($file);
        if (trim($firstLine) !== '[') {
            rewind($file);
        }

        while (($line = fgets($file)) !== false) {
            // Trim and skip empty lines and brackets
            $trimmedLine = trim($line);
            if ($trimmedLine === '' || $trimmedLine === '[' || $trimmedLine === ']') {
                continue;
            }

            if ($currentObjectCount % $objectsPerFile == 0) {
                if ($outputFile) {
                    fseek($outputFile, -1, SEEK_CUR); // Move back to remove the last comma
                    // Close the JSON array in the current part
                    fwrite($outputFile, "\n]");
                    fclose($outputFile);
                }

                $part++;
                $outputFilePrefix = pathinfo($filename, PATHINFO_FILENAME);
                $outputFilePath = storage_path("dumps/{$outputFilePrefix}_part_{$part}.json");
                $outputFile = fopen($outputFilePath, 'w');

                // Start the JSON array in the new part
                fwrite($outputFile, "[\n");
                $currentObjectCount = 0;
            } else {
                fwrite($outputFile, "\n");
            }

            // Write the JSON object to the current part
            fwrite($outputFile, $trimmedLine);
            $currentObjectCount++;
        }

        // Close the final part properly
        if ($outputFile) {
            fseek($outputFile, -1, SEEK_CUR); // Move back to remove the last comma
            fwrite($outputFile, "\n]");
            fclose($outputFile);
        }

        fclose($file);
    }
}
