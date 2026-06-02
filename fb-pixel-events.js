/**
 * Meta Pixel — Eventos personalizados INTEP
 * Pixel ID: 1687597795721435
 *
 * Eventos rastreados automáticamente:
 * - Contact          → clic en cualquier link WhatsApp / tel / mailto
 * - ViewContent      → visita a página de programa técnico
 * - InitiateCheckout → entrada a paginamatriculas/index.html (PIN)
 * - Lead             → envía formulario de matrícula (formulario.html)
 * - CompleteRegistration → llega a bienvenido.html
 */
(function () {
  'use strict';

  // Espera a que fbq esté disponible (cargado por el código base)
  function trackWhenReady(eventName, params) {
    if (typeof fbq === 'function') {
      fbq('track', eventName, params || {});
      console.log('[Meta Pixel] ' + eventName, params || '');
    } else {
      // Reintenta en 200ms si fbq aún no cargó
      setTimeout(function () { trackWhenReady(eventName, params); }, 200);
    }
  }

  // ─────────────────────────────────────────────────────────────
  // 1. CONTACT — clics en WhatsApp, mailto, tel
  // ─────────────────────────────────────────────────────────────
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a');
    if (!a || !a.href) return;
    var href = a.href.toLowerCase();

    if (href.indexOf('wa.me/') !== -1 ||
        href.indexOf('whatsapp.com/') !== -1 ||
        href.indexOf('api.whatsapp.com/') !== -1) {
      trackWhenReady('Contact', {
        content_name: 'WhatsApp',
        content_category: extractWhatsAppNumber(href)
      });
    } else if (href.indexOf('mailto:') === 0) {
      trackWhenReady('Contact', { content_name: 'Email' });
    } else if (href.indexOf('tel:') === 0) {
      trackWhenReady('Contact', { content_name: 'Telefono' });
    }
  }, true);

  function extractWhatsAppNumber(href) {
    var m = href.match(/wa\.me\/(\d+)/);
    return m ? m[1] : 'unknown';
  }

  // ─────────────────────────────────────────────────────────────
  // 2. ViewContent — páginas de programas técnicos
  // ─────────────────────────────────────────────────────────────
  var path = window.location.pathname.toLowerCase();
  var programaMatch = path.match(/\/(cursos|cursos-asincronicos|programas-tecnicos)\/([^/]+)/);

  if (programaMatch) {
    trackWhenReady('ViewContent', {
      content_type: 'programa_tecnico',
      content_category: programaMatch[1],
      content_name: programaMatch[2].replace(/-/g, ' '),
      content_ids: [programaMatch[2]]
    });
  }

  // ─────────────────────────────────────────────────────────────
  // 3. FLUJO DE MATRÍCULA — paginamatriculas/*
  // ─────────────────────────────────────────────────────────────
  if (path.indexOf('/paginamatriculas/') !== -1) {
    if (path.indexOf('/index') !== -1 || path.match(/\/paginamatriculas\/?$/)) {
      trackWhenReady('InitiateCheckout', { content_name: 'Matricula - PIN' });
    } else if (path.indexOf('/formulario') !== -1) {
      // Lead se dispara cuando envíen el formulario (listener abajo)
      trackWhenReady('ViewContent', { content_name: 'Matricula - Formulario' });

      var form = document.querySelector('form');
      if (form) {
        form.addEventListener('submit', function () {
          trackWhenReady('Lead', { content_name: 'Matricula - Datos personales enviados' });
        });
      }
    } else if (path.indexOf('/documentos') !== -1) {
      trackWhenReady('AddPaymentInfo', { content_name: 'Matricula - Documentos' });
    } else if (path.indexOf('/bienvenido') !== -1) {
      trackWhenReady('CompleteRegistration', {
        content_name: 'Matricula completada',
        status: 'completed'
      });
    }
  }

  // ─────────────────────────────────────────────────────────────
  // 4. Formulario de contacto del sitio principal (formsubmit.co)
  // ─────────────────────────────────────────────────────────────
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form && form.action && form.action.indexOf('formsubmit.co') !== -1) {
      trackWhenReady('Lead', { content_name: 'Formulario contacto sitio' });
    }
  }, true);

})();
