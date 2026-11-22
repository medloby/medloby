const pageWidth = 595;
const pageHeight = 842;
const margin = 46;
const scale = 2;
const lineHeight = 17;
const bodyFont = '12px system-ui, sans-serif';

function wrapText(context, text, width) {
    const lines = [];
    for (const paragraph of text.replace(/\r\n?/g, '\n').split('\n')) {
        let line = '';
        for (const word of paragraph.trim().split(/\s+/)) {
            const candidate = line ? line + ' ' + word : word;
            if (context.measureText(candidate).width <= width) {
                line = candidate;
                continue;
            }
            if (line) {
                lines.push(line);
            }
            line = '';
            if (context.measureText(word).width <= width) {
                line = word;
                continue;
            }

            for (const character of word) {
                if (line && context.measureText(line + character).width > width) {
                    lines.push(line);
                    line = '';
                }
                line += character;
            }
        }
        lines.push(line);
    }
    return lines;
}

function paintPages(card) {
    const pages = [];
    let context;
    let position;

    function text(value, font, color) {
        context.font = font;
        context.fillStyle = color;
        context.fillText(value, margin, position);
        position += lineHeight;
    }

    function startPage() {
        const page = document.createElement('canvas');
        page.width = pageWidth * scale;
        page.height = pageHeight * scale;
        context = page.getContext('2d', { alpha: false });
        context.scale(scale, scale);
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, pageWidth, pageHeight);
        context.fillStyle = '#115e59';
        context.fillRect(margin, margin, pageWidth - 2 * margin, 3);
        context.textBaseline = 'top';
        position = margin + 15;
        text('Medloby', 'bold 12px system-ui, sans-serif', '#115e59');
        text('Randevu kartı', 'bold 24px system-ui, sans-serif', '#0f172a');
        position += 14;
        text('Randevu #' + card.id + ' · ' + card.status, bodyFont, '#475569');
        position += 20;
        pages.push(page);
    }

    startPage();
    const fields = [['Klinik ve şube', card.clinic], ...card.fields];
    if (card.notes) {
        fields.push(['Randevu açıklaması', card.notes]);
    }
    for (const [label, value] of fields) {
        context.font = bodyFont;
        const lines = wrapText(context, value, pageWidth - 2 * margin);
        if (position + lineHeight * 2 > pageHeight - margin) {
            startPage();
        }
        text(label, 'bold 11px system-ui, sans-serif', '#475569');
        for (const line of lines) {
            if (position + lineHeight > pageHeight - margin) {
                startPage();
                text(label + ' (devamı)', 'bold 11px system-ui, sans-serif', '#475569');
            }
            text(line, bodyFont, '#0f172a');
        }
        position += 10;
    }
    return pages;
}

async function jpegBytes(page) {
    const image = await new Promise((resolve) => page.toBlob(resolve, 'image/jpeg', 0.94));
    if (!image) {
        throw new Error('Randevu PDF dosyası oluşturulamadı.');
    }
    return new Uint8Array(await image.arrayBuffer());
}

export async function createAppointmentPdf(card) {
    const images = await Promise.all(paintPages(card).map(jpegBytes));
    const encoder = new TextEncoder();
    const chunks = [];
    const offsets = [0];
    let size = 0;

    function append(part) {
        const bytes = typeof part === 'string' ? encoder.encode(part) : part;
        chunks.push(bytes);
        size += bytes.byteLength;
    }

    function object(number, ...parts) {
        offsets[number] = size;
        append(number + ' 0 obj\n');
        for (const part of parts) {
            append(part);
        }
        append('\nendobj\n');
    }

    // Each A4 page has a drawing stream and a JPEG image XObject.
    append('%PDF-1.4\n%âãÏÓ\n');
    object(1, '<< /Type /Catalog /Pages 2 0 R >>');
    const pages = images.map((_, index) => (3 + index * 3) + ' 0 R').join(' ');
    object(2, '<< /Type /Pages /Kids [' + pages + '] /Count ' + images.length + ' >>');

    images.forEach((image, index) => {
        const page = 3 + index * 3;
        const drawing = 'q\n' + pageWidth + ' 0 0 ' + pageHeight + ' 0 0 cm\n/Im0 Do\nQ\n';
        object(page, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + pageWidth + ' ' + pageHeight + ']\n'
            + '/Contents ' + (page + 1) + ' 0 R /Resources << /XObject << /Im0 ' + (page + 2) + ' 0 R >> >> >>');
        object(page + 1, '<< /Length ' + drawing.length + ' >>\nstream\n', drawing, 'endstream');
        object(page + 2, '<< /Type /XObject /Subtype /Image /Width ' + pageWidth * scale + ' /Height ' + pageHeight * scale
            + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + image.length + ' >>\nstream\n', image, '\nendstream');
    });

    const crossReference = size;
    append('xref\n0 ' + offsets.length + '\n0000000000 65535 f \n');
    for (const offset of offsets.slice(1)) {
        append(String(offset).padStart(10, '0') + ' 00000 n \n');
    }
    append('trailer\n<< /Size ' + offsets.length + ' /Root 1 0 R >>\nstartxref\n' + crossReference + '\n%%EOF\n');
    return new Blob(chunks, { type: 'application/pdf' });
}
