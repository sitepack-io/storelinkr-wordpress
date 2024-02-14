function storelinkrDisplayStockLocations(stockInfo) {
    if (stockInfo.stockLocations.length === 0) {
        console.log('No stock locations in result');
        return;
    }

    var html = '<p><button class="storelinkr-stock-button" onclick="toggleStoreLinkrStockTable();">Bekijk winkelvoorraad</button></p>';

    html += '<table class="storelinkr-stock-table" id="storelinkr-stock-table" cellspacing="0" cellpadding="0">';
    html += '<tbody>';
    stockInfo.stockLocations.forEach(function (index) {
        html += '<tr>';
        html += '<td class="location-column">' + index.locationName + '</td>';
        html += '<td class="stock-column">';
        if (index.stockQuantity === 0) {
            html += '<span class="stock-text stock-status-none">Uitverkocht</span>';
        } else {
            html += '<span class="stock-text stock-status-in-stock">Op voorraad</span> ';
            html += '<i>' + index.stockQuantity + ' stuk(s)</i>';
        }
        html += '</td>';
        html += '</tr>';
    });
    html += '</tbody></table>';

    jQuery('#storelinkrStockLocations').html(html);
}

function toggleStoreLinkrStockTable() {
    jQuery('#storelinkr-stock-table').toggle();
}