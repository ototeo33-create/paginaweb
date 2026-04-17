const fs = require('fs');

const htmlFiles = fs.readdirSync('.').filter(f => f.endsWith('.html'));

htmlFiles.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');

    // Remove the custom script just in case
    content = content.replace(/<script src="universe_bg\.js"><\/script>\n?/g, '');

    // Restore dashboard layout
    // We look for background: #f8fafc; and replace with the universe background
    content = content.replace(/\.dashboard-layout\s*\{[^}]*\}/, (match) => {
        let res = match.replace(/background:\s*#[a-fA-F0-9]+;/, "background: url('universe.svg') no-repeat center center fixed;\n            background-size: cover;");
        res = res.replace(/color:\s*#[a-fA-F0-9]+;/, 'color: white;');
        return res;
    });

    // Main content
    content = content.replace(/\.main-content\s*\{[^}]*\}/, (match) => {
        let res = match.replace(/color:\s*#[a-fA-F0-9]+;/, '');
        res = res.replace(/background:\s*#[a-fA-F0-9]+;/, 'background: transparent;');
        return res;
    });

    // Module Card
    content = content.replace(/\.module-card\s*\{[^}]*\}/, (match) => {
        let res = match.replace(/background:\s*#[a-fA-F0-9]+;/, 'background: rgba(255, 255, 255, 0.05);');
        res = res.replace(/color:\s*#[a-fA-F0-9]+;/, 'color: white;');
        res = res.replace(/border:\s*1px solid #[a-fA-F0-9]+;\s*box-shadow:\s*[^;]+;/, 'border: 1px solid rgba(255,255,255,0.15);');
        return res;
    });

    // Card Hover
    content = content.replace(/\.module-card:hover\s*\{[^}]*\}/, (match) => {
        return match.replace(/background:\s*#[a-fA-F0-9]+;/, 'background: rgba(255,255,255,0.1);');
    });

    // Locked Card
    content = content.replace(/\.module-card\.locked\s*\{[^}]*\}/, (match) => {
        return match.replace(/background:\s*#[a-fA-F0-9]+;/, 'background: rgba(0,0,0,0.2);');
    });

    // Sidebar
    content = content.replace(/\.sidebar\s*\{[^}]*\}/, (match) => {
        let res = match.replace(/color:\s*white;\s*/, ''); // Remove the extra color white we added if it was redundant
        res = res.replace(/background:\s*#[a-fA-F0-9]+;/, 'background: rgba(15, 23, 42, 0.6);');
        // Ensure color white is present
        if (!res.includes('color: white;')) {
            res = res.replace('background: rgba(15, 23, 42, 0.6);', 'background: rgba(15, 23, 42, 0.6);\n            color: white;');
        }
        return res;
    });

    // Module Num
    content = content.replace(/\.module-num\s*\{[^}]*\}/, (match) => {
        let res = match.replace(/color:\s*#[a-fA-F0-9]+;\s*font-weight:\s*bold;/, 'color: var(--primary-dark);');
        return res;
    });

    // Body (for arcade pages)
    content = content.replace(/body\s*\{[^}]*\}/, (match) => {
        if (match.includes('background: #ffffff;')) {
            return match.replace('background: #ffffff;', "background: url('universe.svg') no-repeat center center fixed;\n        background-size: cover;");
        }
        return match;
    });

    // Text colors
    content = content.replace(/color:\s*#1e293b;/g, 'color: white;');
    content = content.replace(/color:\s*#0f172a;/g, 'color: white;');

    // For index.html
    if (file === 'index.html') {
         content = content.replace('background: #ffffff;', '');
    }

    // specific stat-cards
    content = content.replace(/background:\s*#ffffff;/g, 'background: rgba(255,255,255,0.05);');

    fs.writeFileSync(file, content);
});

// Revert CSS files
const cssFiles = ['lesson.css', 'index.css'];
cssFiles.forEach(file => {
    if (fs.existsSync(file)) {
        let content = fs.readFileSync(file, 'utf8');
        content = content.replace('background: #ffffff;', "background: url('universe.svg') no-repeat center center fixed;");
        content = content.replace(/color:\s*#334155;/g, 'color: #fff;');
        content = content.replace(/color:\s*#334155/g, 'color: #fff');
        content = content.replace(/--text-muted:\s*#64748b;/g, '--text-muted: #cbd5e1;');
        
        content = content.replace('background: #ffffff; border-bottom: 1px solid #e2e8f0;', 'background: rgba(0, 0, 0, 0.8);');

        fs.writeFileSync(file, content);
    }
});

console.log('Revertido al tema oscuro original universe.svg exitosamente.');
