


function style(invoice)
{
    var GlobalY=0;//Y position of line at current page
    var invoice=invoice.invoice;
    var client = invoice.client;
    var account = invoice.account;
    var currencyId = client.currency_id;

    var layout = {
        accountTop: 40,
        marginLeft: 50,
        marginRight: 550,
        headerTop: 150,
        headerLeft: 360,
        headerRight: 550,
        rowHeight: 15,
        tableRowHeight: 10,
        footerLeft: 420,
        tablePadding: 12,
        tableTop: 250,
        descriptionLeft: 162,
        unitCostRight: 410,
        qtyRight: 480,
        taxRight: 480,
        lineTotalRight: 550
    };

    if (invoice.has_taxes)
    {
        layout.descriptionLeft -= 20;
        layout.unitCostRight -= 40;
        layout.qtyRight -= 40;
    }

    /*
     @param orientation One of "portrait" or "landscape" (or shortcuts "p" (Default), "l")
     @param unit Measurement unit to be used when coordinates are specified. One of "pt" (points), "mm" (Default), "cm", "in"
     @param format One of 'a3', 'a4' (Default),'a5' ,'letter' ,'legal'
     @returns {jsPDF}
     */
    var doc = new jsPDF('portrait', 'pt', 'a4');

    //doc.getStringUnitWidth = function(param) { console.log('getStringUnitWidth: %s', param); return 0};

    //Set PDF properities
    doc.setProperties({
        title: 'Invoice ' + invoice.invoice_number,
        subject: '',
        author: 'InvoiceNinja.com',
        keywords: 'pdf, invoice',
        creator: 'InvoiceNinja.com'
    });

    //set default style for report
    doc.setFont('Helvetica','');

    layout.headerRight = 550;
    layout.rowHeight = 15;

    doc.setFontSize(9);

    if (invoice.image)
    {
        var left = layout.headerRight - invoice.imageWidth;
        doc.addImage(invoice.image, 'JPEG', layout.marginLeft, 30);
    }

    if (!invoice.is_pro && logoImages.imageLogo1)
    {
        pageHeight=820;
        y=pageHeight-logoImages.imageLogoHeight1;
        doc.addImage(logoImages.imageLogo1, 'JPEG', layout.marginLeft, y, logoImages.imageLogoWidth1, logoImages.imageLogoHeight1);
    }

    doc.setFontSize(9);
    SetPdfColor('LightBlue', doc, 'primary');
    displayAccount(doc, invoice, 220, layout.accountTop, layout);

    SetPdfColor('LightBlue', doc, 'primary');
    doc.setFontSize('11');
    doc.text(50, layout.headerTop, (invoice.is_quote ? invoiceLabels.quote : invoiceLabels.invoice).toUpperCase());


    SetPdfColor('Black',doc); //set black color
    doc.setFontSize(9);

    var invoiceHeight = displayInvoice(doc, invoice, 50, 170, layout);
    var clientHeight = displayClient(doc, invoice, 220, 170, layout);
    var detailsHeight = Math.max(invoiceHeight, clientHeight);
    layout.tableTop = Math.max(layout.tableTop, layout.headerTop + detailsHeight + (3 * layout.rowHeight));

    doc.setLineWidth(0.3);
    doc.setDrawColor(200,200,200);
    doc.line(layout.marginLeft - layout.tablePadding, layout.headerTop + 6, layout.marginRight + layout.tablePadding, layout.headerTop + 6);
    doc.line(layout.marginLeft - layout.tablePadding, layout.headerTop + detailsHeight + 14, layout.marginRight + layout.tablePadding, layout.headerTop + detailsHeight + 14);

    doc.setFontSize(10);
    doc.setFontType('bold');
    displayInvoiceHeader(doc, invoice, layout);
    var y = displayInvoiceItems(doc, invoice, layout);

    doc.setFontSize(9);
    doc.setFontType('bold');

    GlobalY=GlobalY+25;


    doc.setLineWidth(0.3);
    doc.setDrawColor(241,241,241);
    doc.setFillColor(241,241,241);
    var x1 = layout.marginLeft - 12;
    var y1 = GlobalY-layout.tablePadding;

    var w2 = 510 + 24;
    var h2 = doc.internal.getFontSize()*3+layout.tablePadding*2;

    if (invoice.discount) {
        h2 += doc.internal.getFontSize()*2;
    }
    if (invoice.tax_amount) {
        h2 += doc.internal.getFontSize()*2;
    }

    //doc.rect(x1, y1, w2, h2, 'FD');

    doc.setFontSize(9);
    displayNotesAndTerms(doc, layout, invoice, y);
    y += displaySubtotals(doc, layout, invoice, y, layout.unitCostRight);


    doc.setFontSize(10);
    Msg = invoice.is_quote ? invoiceLabels.total : invoiceLabels.balance_due;
    var TmpMsgX = layout.unitCostRight-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());

    doc.text(TmpMsgX, y, Msg);

    SetPdfColor('LightBlue', doc, 'primary');
    AmountText = formatMoney(invoice.balance_amount, currencyId);
    headerLeft=layout.headerRight+400;
    var AmountX = layout.lineTotalRight - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
    doc.text(AmountX, y, AmountText);

    return doc;
}

function savePdf(invoice)
{
    doc=style(invoice);
    doc.save('Invoice-' + $('#invoice_number').val() + '.pdf');
}