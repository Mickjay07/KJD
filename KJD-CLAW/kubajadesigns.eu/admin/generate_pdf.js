const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

async function generatePDF(htmlPath, pdfPath) {
    try {
        const browser = await puppeteer.launch({
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
        
        const page = await browser.newPage();
        
        // Load HTML file
        const htmlContent = fs.readFileSync(htmlPath, 'utf8');
        await page.setContent(htmlContent, { waitUntil: 'networkidle0' });
        
        // Generate PDF
        await page.pdf({
            path: pdfPath,
            format: 'A4',
            margin: {
                top: '10mm',
                right: '10mm',
                bottom: '10mm',
                left: '10mm'
            },
            printBackground: true,
            preferCSSPageSize: true
        });
        
        await browser.close();
        
        // Check if PDF was created successfully
        if (fs.existsSync(pdfPath) && fs.statSync(pdfPath).size > 0) {
            console.log('PDF generated successfully:', pdfPath);
            return true;
        } else {
            console.log('PDF generation failed - file not created or empty');
            return false;
        }
        
    } catch (error) {
        console.error('Error generating PDF:', error.message);
        return false;
    }
}

// Get command line arguments
const args = process.argv.slice(2);
if (args.length < 2) {
    console.error('Usage: node generate_pdf.js <html_path> <pdf_path>');
    process.exit(1);
}

const htmlPath = args[0];
const pdfPath = args[1];

// Check if HTML file exists
if (!fs.existsSync(htmlPath)) {
    console.error('HTML file not found:', htmlPath);
    process.exit(1);
}

// Generate PDF
generatePDF(htmlPath, pdfPath).then(success => {
    process.exit(success ? 0 : 1);
});
