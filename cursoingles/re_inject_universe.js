const fs = require('fs');

const cssFiles = ['lesson.css', 'index.css'];
cssFiles.forEach(file => {
    if (fs.existsSync(file)) {
        let content = fs.readFileSync(file, 'utf8');
        // Quitar universe.svg de los CSS globales para que se vea el webgl/canvas interactivo
        content = content.replace(/background:\s*url\('universe\.svg'\)[^;]+;/g, 'background: transparent;');
        content = content.replace(/background:\s*#ffffff;/g, 'background: transparent;');
        
        // Colores oscuros de vuelta a blanco/claro
        content = content.replace(/color:\s*#334155;/g, 'color: #fff;');
        content = content.replace(/color:\s*#334155/g, 'color: #fff');
        content = content.replace(/color:\s*#1e293b;/g, 'color: #fff;');
        
        // Bordes de menu navbar
        content = content.replace(/background:\s*#ffffff;\s*border-bottom:\s*1px\s*solid\s*#e2e8f0;/g, 'background: rgba(0, 0, 0, 0.8);');

        fs.writeFileSync(file, content);
    }
});

const htmlFiles = fs.readdirSync('.').filter(f => f.endsWith('.html'));

htmlFiles.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');

    // 1. Quitar todos los universe.svg en línea (dashboard-layout, body, etc) para que deje ver el canvas interactivo
    content = content.replace(/background:\s*url\('universe\.svg'\)[^;]*;/gi, 'background: transparent;');
    content = content.replace(/background:\s*#ffffff;/gi, 'background: transparent;');
    content = content.replace(/background:\s*#f8fafc;/gi, 'background: transparent;');

    // 2. Colores de texto principales
    content = content.replace(/color:\s*#1e293b;/gi, 'color: white;');
    content = content.replace(/color:\s*#0f172a;/gi, 'color: white;');

    // 3. Main content backgrounds transparentes
    content = content.replace(/\.main-content\s*\{[^}]*\}/g, (match) => {
        let m = match;
        if (!m.includes('background: transparent')) {
            m = m.replace(/background:[^;]+;/, 'background: transparent;');
        }
        return m;
    });

    // 4. Inyectar uniformemente el <script src="universe_bg.js"></script>
    if (!content.includes('universe_bg.js')) {
        content = content.replace('</body>', '<script src="universe_bg.js"></script>\n</body>');
    }

    // 5. Restablecer estilos de tarjetas
    content = content.replace(/\.module-card\s*\{[^}]*\}/g, (match) => {
        let m = match.replace(/background:[^;]+;/, 'background: rgba(255, 255, 255, 0.05);');
        m = m.replace(/border:[^;]+;/i, 'border: 1px solid rgba(255,255,255,0.15);');
        m = m.replace(/box-shadow:[^;]+;/i, '');
        return m;
    });

    content = content.replace(/\.module-card:hover\s*\{[^}]*\}/g, (match) => {
        return match.replace(/background:[^;]+;/, 'background: rgba(255, 255, 255, 0.1);');
    });

    content = content.replace(/\.module-card\.locked\s*\{[^}]*\}/g, (match) => {
        return match.replace(/background:[^;]+;/, 'background: rgba(0,0,0,0.2);');
    });

    fs.writeFileSync(file, content);
});

console.log('Fondo mágico implementado uniformemente a través de la plataforma.');
