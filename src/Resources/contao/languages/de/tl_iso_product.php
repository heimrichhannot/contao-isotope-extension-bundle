<?php

$lang = &$GLOBALS['TL_LANG']['tl_iso_product'];

/**
 * Fields
 */
$lang['initialStock']            = ['Anfangsbestand', 'Geben Sie hier den Anfangsbestand des Produkts ein.'];
$lang['stock']                   = ['Bestand', 'Geben Sie hier den Bestand des Produkts ein.'];
$lang['releaseDate']             = ['Erscheinungsdatum', 'Geben Sie hier Erscheinungsdatum des Produkts ein.'];
$lang['maxOrderSize']            = ['Maximale Bestellmenge', 'Geben Sie hier die maximale Bestellmenge ein.'];
$lang['setQuantity']             = ['Set', 'Geben Sie hier ein, wie viele Artikel zusammen im Set verkauft werden.'];
$lang['overrideStockShopConfig'] = [
    'Bestandskonfiguration überschreiben',
    'Wählen Sie diese Option, um die Konfiguration des Bestands, die Sie im Produkttyp bzw. in der aktuellen Shop-Konfiguration gesetzt haben, zu überschreiben.',
];
$lang['jumpTo']                  = ['Weiterleitungsseite', 'Wählen Sie hier die Weiterleitungsseite aus.'];
$lang['addedBy']                 = ['Hinzugefügt durch', 'Tragen Sie hier ein, wer den Artikel hochgeladen hat.'];
$lang['downloadCount']           = ['Downloads', ''];
$lang['relevance']               = ['Beliebtheit', ''];

// TODO: Needed?
$lang['bookingStart']         = ['Buchungszeitraum-Start', 'Wählen Sie hier den Beginn der Buchung aus.'];
$lang['bookingStop']          = ['Buchungszeitraum-Ende', 'Wählen Sie hier das Ende der Buchung aus.'];
$lang['bookingBlock']         = [
    'Artikel vor/nach Bestellung blockieren',
    'Tragen Sie hier die Anzahl der Tage ein die ein Artikel nach seiner Buchung gesperrt sein soll. Dies kann bspw. benötigt werden wenn ein Artikel für einen Buchungszeitraum gebucht wird und nach dem Buchungszeitraum aus logistischn Gründen für eine Zeit gesperrt ist.',
];
$lang['bookingReservedDates'] = [
    'Produkt-Reservierungen',
    'Sie können hier Zeiträume hinterlegen, für die das Produkt reserviert sein soll. Die angegebenen Daten werden in die Berechnung der gesperrten Tage des Produktes aufgenommen.',
];
$lang['useCount'] = ['Anzahl festlegen', 'Wählen Sie diese Option, wenn Sie die Anzahl der reservierten Produkte angeben wollen. Wenn Sie diese Option nicht wählen, werden alle auf Lager befindlichen Produkte reserviert.'];
$lang['count']    = ['Anzahl', 'Wählen Sie hier die Anzahl der zu reservierenden Produkte aus.'];
