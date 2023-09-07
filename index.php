<?php

require_once __DIR__ . '/i_doklad/iDokladController.php';

try {
    if (file_exists(__DIR__ . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/');
    } else {
        throw new Exception('The .env file was not found in parent directory');
    }

    echo '=== Začátek převádění faktur ===<br>';

    $dotenv->load();

    $iDokladController = new IDokladController();
    $countries = $iDokladController->getCountries();

    $invoices = $iDokladController->getInvoices(1);
    $xmlDom = new DOMDocument('1.0', 'utf-8');
    $xmlInvoices = $xmlDom->createElement('invoices');
    $totalTotalValue = 0;
    $totalTotalValueItems = 0;

    for ($i = 1; $i <= $invoices->Data->TotalPages; $i++) {
        if ($i == 5) {
            return;
            echo '<br>!!KONEC!!<br>';
        }
        echo '=== Probíhá načtení ' . $i . ' z ' . $invoices->Data->TotalPages . ' strany seznamu faktur ===<br>';

        if (!isset($invoices->Data->Items)) {
            throw new Exception('Invoices array is not set');
        }

        $invoices = $iDokladController->getInvoices($i);
        $invoicesArray = $invoices->Data->Items;
        for ($j = 0; $j < count($invoicesArray); $j++) {
            $xmlOneInvoice = $xmlDom->createElement('invoice');
            $invoiceDocument = $invoicesArray[$j];
            $totalTotalValue = $totalTotalValue + floatval($invoiceDocument->Prices->TotalWithoutVat);

            try {
                // $xmlOneInvoice->appendChild($xmlDom->createElement('totalPrice', $invoiceDocument->Prices->TotalWithoutVat));
                $xmlOneInvoice->appendChild($xmlDom->createElement('kind', 'issued'));
                $xmlOneInvoice->appendChild($xmlDom->createElement('number', $invoiceDocument->DocumentNumber));
                $xmlOneInvoice->appendChild($xmlDom->createElement('vendor_number', $invoiceDocument->VariableSymbol));
                $xmlOneInvoice->appendChild($xmlDom->createElement('description', $invoiceDocument->Description));

                $xmlOneInvoice->appendChild($xmlDom->createElement('date_of_issue', $invoiceDocument->DateOfIssue));
                $xmlOneInvoice->appendChild($xmlDom->createElement('date_of_tax', $invoiceDocument->DateOfTaxing));
                $xmlOneInvoice->appendChild($xmlDom->createElement('date_of_payment', $invoiceDocument->DateOfPayment));

                $xmlOneInvoice->appendChild($xmlDom->createElement('payment_type', 'transfer'));
                $xmlOneInvoice->appendChild($xmlDom->createElement('currency', 'CZK'));
                $xmlOneInvoice->appendChild($xmlDom->createElement('reference_number', $invoiceDocument->VariableSymbol));
                $xmlOneInvoice->appendChild($xmlDom->createElement('text_before', $invoiceDocument->ItemsTextPrefix));
                $xmlOneInvoice->appendChild($xmlDom->createElement('text_after', $invoiceDocument->ItemsTextSuffix));

                $reverse_charge = 'false';
                if ($invoiceDocument->PartnerAddress->CountryId !== 2) {
                    if ($invoiceDocument->Prices->TotalVat == 0) {
                        $reverse_charge = 'true';
                    }
                }

                $xmlOneInvoice->appendChild($xmlDom->createElement('reverse_charge', $reverse_charge));
                $xmlOneInvoice->appendChild($xmlDom->createElement('paid', 'true'));

                $xmlCompany = $xmlDom->createElement('company');
                $xmlCompany->appendChild($xmlDom->createElement('name', htmlspecialchars($invoiceDocument->PartnerAddress->NickName)));
                $xmlCompany->appendChild($xmlDom->createElement('id', $invoiceDocument->PartnerAddress->IdentificationNumber));
                $xmlCompany->appendChild($xmlDom->createElement('tax_id', $invoiceDocument->PartnerAddress->VatIdentificationNumber));

                $vat_payer = 'true';
                if (empty($invoiceDocument->PartnerAddress->VatIdentificationNumber)) {
                    $vat_payer = 'false';
                }
                $xmlCompany->appendChild($xmlDom->createElement('vat_payer', $vat_payer));
                $xmlCompany->appendChild($xmlDom->createElement('street', $invoiceDocument->PartnerAddress->Street));
                $xmlCompany->appendChild($xmlDom->createElement('zip', $invoiceDocument->PartnerAddress->PostalCode));
                $xmlCompany->appendChild($xmlDom->createElement('city', $invoiceDocument->PartnerAddress->City));
                $xmlCompany->appendChild($xmlDom->createElement('country_code', $countries[$invoiceDocument->PartnerAddress->CountryId]));

                $companyKind = 'individual';
                if ($iDokladController->isLegalEntity($invoiceDocument->PartnerAddress)) {
                    $companyKind = 'legal_entity';
                }
                $xmlCompany->appendChild($xmlDom->createElement('kind', $companyKind));

                $xmlOneInvoice->appendChild($xmlCompany);
                $xmlItems = $xmlDom->createElement('items');
                $totalPriceItems = 0;
                foreach ($invoiceDocument->Items as $item) {
                    $xmlOneItem = $xmlDom->createElement('item');

                    $discountPerc = 0;
                    if (!empty($item->DiscountPercentage) && floatval($item->DiscountPercentage) != 0) {
                        $discountPerc = floatval($item->DiscountPercentage);
                        echo $discountPerc . '<br>';
                    }

                    $unitPrice = $item->Prices->UnitPrice;
                    if ($discountPerc != 0) {
                        $unitPrice = round($unitPrice / 100 * (100 - $discountPerc));
                        echo '<br>' . $unitPrice . '<br>';
                    }

                    $xmlOneItem->appendChild($xmlDom->createElement('name', $item->Name));
                    $xmlOneItem->appendChild($xmlDom->createElement('quantity', $item->Amount));
                    $xmlOneItem->appendChild($xmlDom->createElement('unit', $item->Unit));
                    $xmlOneItem->appendChild($xmlDom->createElement('price', $unitPrice));
                    $totalPriceItems = $totalPriceItems + (floatval($item->Amount) * floatval($item->Prices->UnitPrice));
                    $totalTotalValueItems = $totalTotalValueItems + (floatval($item->Amount) * floatval($unitPrice));

                    $vat_rate = 21;
                    if ($reverse_charge === 'true') {
                        $vat_rate = 0;
                    }
                    $xmlOneItem->appendChild($xmlDom->createElement('vat_rate', $vat_rate));

                    $xmlItems->appendChild($xmlOneItem);
                }

                if (count($invoiceDocument->Items) == 0) {
                    $xmlOneItem = $xmlDom->createElement('item');
                    $xmlOneItem->appendChild($xmlDom->createElement('name', 'Služba'));
                    $xmlOneItem->appendChild($xmlDom->createElement('quantity', 1));
                    $xmlOneItem->appendChild($xmlDom->createElement('unit', 'ks'));
                    $xmlOneItem->appendChild($xmlDom->createElement('price', $invoiceDocument->Prices->TotalWithoutVat));
                    $totalPriceItems = $invoiceDocument->Prices->TotalWithoutVat;
                    $totalTotalValueItems = $totalTotalValueItems + floatval($invoiceDocument->Prices->TotalWithoutVat);

                    $vat_rate = 21;
                    if ($reverse_charge === 'true') {
                        $vat_rate = 0;
                    }
                    $xmlOneItem->appendChild($xmlDom->createElement('vat_rate', $vat_rate));

                    $xmlItems->appendChild($xmlOneItem);
                }
                // $xmlOneInvoice->appendChild($xmlDom->createElement('totalPriceItems', $totalPriceItems));
                $xmlOneInvoice->appendChild($xmlItems);
                $xmlInvoices->appendChild($xmlOneInvoice);
            } catch (Exception $e) {
                echo 'Při zpracování faktury č. ' . $invoiceDocument->DocumentNumber . ' došlo k chybě<br>';
                echo $e->getMessage() . '<br>';
            }
        }
    }

    $xmlDom->appendChild($xmlInvoices);
    $xmlString = $xmlDom->saveXML();

    if (file_exists('test.xml')) {
        unlink('test.xml');
    }
    $file = fopen('test.xml', 'w');
    if ($file) {
        fwrite($file, $xmlString);
        fclose($file);
    }

    echo $totalTotalValue;
    echo '<br>';
    echo $totalTotalValueItems;
} catch (Exception $ex) {
    echo $ex->getMessage() . ' ' . $ex->getTraceAsString() . '; ' . $ex->getFile() . ' ' . $ex->getLine();
}
