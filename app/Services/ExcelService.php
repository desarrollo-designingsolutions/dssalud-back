<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExcelService
{
    /**
     * Get all sheets.
     *
     * @deprecated
     *
     * @throws Exception
     */
    public function getSheetsFromExcel(string $path): array
    {
        /**  Identify the type of $inputFileName  **/
        $inputFileType = IOFactory::identify($path);

        /**  Create a new Reader of the type that has been identified  **/
        $reader = IOFactory::createReader($inputFileType);

        /**  Load $inputFileName to a Spreadsheet Object  **/
        $spreadsheet = $reader->load($path);

        /**  Convert Spreadsheet Object to an Array for ease of use  **/
        return $spreadsheet->getAllSheets();
    }

    /**
     * Get all sheets.
     *
     * @throws Exception
     */
    public function getSpreadsheetFromExcel(string $path): Spreadsheet
    {
        /**  Identify the type of $inputFileName  **/
        $inputFileType = IOFactory::identify($path);

        /**  Create a new Reader of the type that has been identified  **/
        $reader = IOFactory::createReader($inputFileType);

        /**  Load $inputFileName to a Spreadsheet Object  **/
        /**  Convert Spreadsheet Object to an Array for ease of use  **/
        return $reader->load($path);
    }

    /**
     * Get all sheets.
     */
    public function getDataFromSheetExcel(int $index, array $sheets): array
    {
        $dataSheet = ($sheets[$index]->toArray());
        unset($dataSheet[0]); // remove titles

        return $dataSheet;
    }
}
