<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIMatic_File_Parser {

    /**
     * Parse file and return text content
     * 
     * @param string $file_path Absolute path
     * @param string $extension File extension
     * @return string|WP_Error Content or Error
     */
    public static function parse($file_path, $extension) {
        $ext = strtolower($extension);
        
        // Ensure Zip module exists
        if (!class_exists('ZipArchive')) {
            return new WP_Error('missing_zip', 'PHP ZipArchive module is required to parse Office files.');
        }

        if ($ext === 'docx') {
            return self::parse_docx($file_path);
        } elseif ($ext === 'xlsx') {
            return self::parse_xlsx($file_path);
        }
        
        return new WP_Error('invalid_format', 'Unsupported file format.');
    }

    /**
     * Parse DOCX
     */
    private static function parse_docx($file_path) {
        $content = '';
        $zip = new ZipArchive();
        
        if ($zip->open($file_path) === true) {
            // Document text is in word/document.xml
            $xml_index = $zip->locateName('word/document.xml');
            
            if ($xml_index !== false) {
                $xml_data = $zip->getFromIndex($xml_index);
                $zip->close();
                
                // Strip XML tags to get raw text
                // We add newlines for paragraphs to keep structure
                $xml_data = str_replace('</w:p>', "\n", $xml_data);
                $content = strip_tags($xml_data);
                
                return trim($content);
            }
            $zip->close();
        }
        
        return new WP_Error('parse_error', 'Failed to read DOCX file.');
    }

    /**
     * Parse XLSX
     * Reads all shared strings and sheet data to reconstruct cell values.
     * Simplified implementation: Reads the first sheet.
     */
    private static function parse_xlsx($file_path) {
        $zip = new ZipArchive();
        $strings = array();
        $content = array();
        
        if ($zip->open($file_path) === true) {
            // 1. Read Shared Strings (Dictionary)
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                $xml = $zip->getFromName('xl/sharedStrings.xml');
                $dom = new DOMDocument();
                $dom->loadXML($xml);
                $nodes = $dom->getElementsByTagName('t');
                foreach ($nodes as $node) {
                    $strings[] = $node->nodeValue;
                }
            }
            
            // 2. Read Sheet 1
            if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
                $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
                $dom = new DOMDocument();
                $dom->loadXML($xml);
                $rows = $dom->getElementsByTagName('row');
                
                foreach ($rows as $row) {
                    $cells = $row->getElementsByTagName('c');
                    foreach ($cells as $cell) {
                        $type = $cell->getAttribute('t');
                        $value_node = $cell->getElementsByTagName('v')->item(0);
                        
                        if ($value_node) {
                            $value = $value_node->nodeValue;
                            
                            // If type is 's', look up in shared strings
                            if ($type === 's' && isset($strings[$value])) {
                                $content[] = $strings[$value];
                            } else {
                                $content[] = $value;
                            }
                        }
                    }
                }
                $zip->close();
                return implode("\n", $content);
            }
            
            $zip->close();
        }
        
        return new WP_Error('parse_error', 'Failed to read XLSX file.');
    }
}
