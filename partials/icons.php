<?php
/**
 * Iconos SVG custom para el portal estudiantil INTEP.
 * Estilo: stroke 1.8, line-cap round, currentColor (hereda el color del padre).
 * Uso: <?= icon('notas') ?> o <?= icon('home', ['size' => 28]) ?>
 */
if (!function_exists('icon')) {
    function icon($name, $opts = []) {
        $size = $opts['size'] ?? 24;
        $sw   = $opts['stroke'] ?? 1.8;
        $cls  = $opts['class'] ?? '';
        $base = 'xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'.$sw.'" stroke-linecap="round" stroke-linejoin="round"'.($cls ? ' class="'.htmlspecialchars($cls).'"' : '');

        $paths = [
            'home'        => '<path d="M3 11.5L12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/>',
            'notas'       => '<rect x="4" y="4" width="16" height="16" rx="2.5"/><path d="M8 9h6"/><path d="M8 13h8"/><path d="M8 17h5"/><path d="M16 5v3"/><circle cx="17.5" cy="6.5" r=".7" fill="currentColor"/>',
            'horario'     => '<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17"/><path d="M8 3v4"/><path d="M16 3v4"/><circle cx="12" cy="14.5" r="2"/><path d="M12 13.5v1l.7.6"/>',
            'cartera'     => '<rect x="3" y="6.5" width="18" height="13" rx="2.5"/><path d="M3 11h18"/><path d="M7.5 16h3"/>',
            'asistencia'  => '<rect x="4" y="4" width="16" height="16" rx="2.5"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
            'solicitudes' => '<path d="M8 3h8a2 2 0 0 1 2 2v15a1 1 0 0 1-1.5.85L12 18.5l-4.5 2.35A1 1 0 0 1 6 20V5a2 2 0 0 1 2-2z"/><path d="M9.5 9h5"/><path d="M9.5 12.5h3.5"/>',
            'materiales'  => '<path d="M4 5a2 2 0 0 1 2-2h4v15H6a2 2 0 0 1-2-2z"/><path d="M14 3h4a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-4z"/><path d="M14 3v15"/>',
            'evaluar'     => '<path d="m12 3 2.7 5.5 6 .9-4.4 4.3 1 6-5.3-2.8L6.7 19.7l1-6L3.3 9.4l6-.9z"/>',
            'ingles'      => '<circle cx="12" cy="12" r="9"/><path d="M3.5 12h17"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/>',
            'kids'        => '<circle cx="12" cy="9" r="4"/><path d="M5 21c0-3.6 3.1-6.5 7-6.5s7 2.9 7 6.5"/><circle cx="10.5" cy="9" r=".7" fill="currentColor"/><circle cx="13.5" cy="9" r=".7" fill="currentColor"/><path d="M10.5 11.2c.5.5 2 .5 3 0"/>',
            'almacen'     => '<path d="M3 8.5 12 4l9 4.5V19a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M3 8.5 12 13l9-4.5"/><path d="M12 13v7"/>',
            'app'         => '<rect x="6" y="3" width="12" height="18" rx="2.5"/><path d="M10 6.5h4"/><circle cx="12" cy="17.5" r=".9" fill="currentColor"/><path d="m9 11 3 3 3-3"/><path d="M12 7v7"/>',
            'perfil'      => '<circle cx="12" cy="8.5" r="3.8"/><path d="M5 20.5c.5-3.6 3.5-6 7-6s6.5 2.4 7 6"/>',
            'arrow'       => '<path d="M9 6l6 6-6 6"/>',
            'logout'      => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M10 16l-4-4 4-4"/><path d="M6 12h11"/>',
            'lock'        => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
            'camera'      => '<path d="M4 7.5h3l1.8-2h6.4L17 7.5h3a1 1 0 0 1 1 1V18a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8.5a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/>',
            'star'        => '<path d="m12 3 2.7 5.5 6 .9-4.4 4.3 1 6-5.3-2.8L6.7 19.7l1-6L3.3 9.4l6-.9z" fill="currentColor" fill-opacity=".18"/>',
            'flame'       => '<path d="M12 3.5c2 3.5 5 5.5 5 9.5a5 5 0 0 1-10 0c0-2 .8-3 2-4 .5 1 1.5 1.5 2.5 1.5 0-3 .5-5 .5-7z"/>',
            'check'       => '<path d="m5 12.5 4.5 4.5L19 7"/>',
            'bell'        => '<path d="M6 16.5V11a6 6 0 1 1 12 0v5.5l1.5 2H4.5z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
            'mensajes'    => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        ];
        $body = $paths[$name] ?? '<circle cx="12" cy="12" r="9"/>';
        return '<svg '.$base.'>'.$body.'</svg>';
    }
}
