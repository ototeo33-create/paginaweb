// ===== SIMULATED STUDENT DATA =====
const STUDENTS = {
  '1001': {
    name: 'Camila Torres',
    program: 'Sistemas',
    semester: '3er semestre',
    pin: '1234',
    photo: null,
    attendance: { present: 42, absent: 3, total: 45, streak: 8 },
    payments: {
      monthly: 180000,
      paid: [1, 2, 3, 4, 5],
      current: 6,
      total: 12,
      dueDate: '2026-03-25',
      status: 'pending' // pending, paid, overdue
    },
    schedule: [
      { time: '7:00', period: 'AM', subject: 'Programación Web', teacher: 'Prof. García', room: 'Lab 3' },
      { time: '9:00', period: 'AM', subject: 'Base de Datos', teacher: 'Prof. Martínez', room: 'Aula 201' },
      { time: '11:00', period: 'AM', subject: 'Redes', teacher: 'Prof. López', room: 'Lab 1' }
    ],
    calendarDays: generateCalendarDays(3, 42, [5, 12, 18]),
    grades: { average: 4.2, best: 'Programación Web', worst: 'Redes' }
  },
  '1002': {
    name: 'Santiago Ruiz',
    program: 'Contabilidad',
    semester: '2do semestre',
    pin: '1234',
    attendance: { present: 38, absent: 7, total: 45, streak: 2 },
    payments: {
      monthly: 180000,
      paid: [1, 2, 3],
      current: 4,
      total: 12,
      dueDate: '2026-03-20',
      status: 'overdue'
    },
    schedule: [
      { time: '2:00', period: 'PM', subject: 'Contabilidad General', teacher: 'Prof. Rodríguez', room: 'Aula 105' },
      { time: '4:00', period: 'PM', subject: 'Matemática Financiera', teacher: 'Prof. Herrera', room: 'Aula 107' },
      { time: '6:00', period: 'PM', subject: 'Legislación Comercial', teacher: 'Prof. Díaz', room: 'Aula 109' }
    ],
    calendarDays: generateCalendarDays(7, 38, [2, 6, 9, 13, 17, 20, 23]),
    grades: { average: 3.5, best: 'Contabilidad General', worst: 'Matemática Financiera' }
  },
  '1003': {
    name: 'Valentina Peña',
    program: 'Admón. de Empresas',
    semester: '1er semestre',
    pin: '1234',
    attendance: { present: 45, absent: 0, total: 45, streak: 45 },
    payments: {
      monthly: 180000,
      paid: [1, 2, 3, 4, 5, 6],
      current: 7,
      total: 12,
      dueDate: '2026-04-05',
      status: 'paid'
    },
    schedule: [
      { time: '7:00', period: 'AM', subject: 'Introducción a la Admón.', teacher: 'Prof. Castro', room: 'Aula 301' },
      { time: '9:00', period: 'AM', subject: 'Microeconomía', teacher: 'Prof. Vargas', room: 'Aula 303' },
      { time: '11:00', period: 'AM', subject: 'Fundamentos de Mercadeo', teacher: 'Prof. Ríos', room: 'Aula 305' }
    ],
    calendarDays: generateCalendarDays(0, 45, []),
    grades: { average: 4.8, best: 'Todas las materias', worst: 'N/A' }
  }
};

function generateCalendarDays(absentCount, presentCount, absentDays) {
  const days = [];
  const firstDay = new Date(2026, 2, 1).getDay(); // March 2026
  const totalDays = 31;

  for (let i = 0; i < firstDay; i++) days.push({ day: 0, status: 'empty' });

  for (let d = 1; d <= totalDays; d++) {
    const dow = new Date(2026, 2, d).getDay();
    if (dow === 0 || dow === 6) {
      days.push({ day: d, status: 'weekend' });
    } else if (d > 19) {
      days.push({ day: d, status: 'future' });
    } else if (absentDays.includes(d)) {
      days.push({ day: d, status: 'absent' });
    } else {
      days.push({ day: d, status: 'present' });
    }
  }
  return days;
}

// ===== APP STATE =====
let currentStudent = null;
let currentPage = 'home';

// ===== MASCOT SVG GENERATOR =====
function getMascotSVG(mood) {
  const colors = {
    happy: { body: '#059669', cheek: '#FFB7B7', eye: '#2D2235', mouth: '#2D2235' },
    worried: { body: '#F59E0B', cheek: '#FFD4A8', eye: '#2D2235', mouth: '#2D2235' },
    sad: { body: '#EF4444', cheek: '#FFC4C4', eye: '#2D2235', mouth: '#2D2235' },
    alert: { body: '#F59E0B', cheek: '#FFE0B2', eye: '#2D2235', mouth: '#2D2235' },
    perfect: { body: '#059669', cheek: '#FFB7B7', eye: '#2D2235', mouth: '#2D2235' }
  };
  const c = colors[mood] || colors.happy;

  const faces = {
    happy: `
      <ellipse cx="35" cy="48" rx="4" ry="5" fill="${c.eye}"/>
      <ellipse cx="65" cy="48" rx="4" ry="5" fill="${c.eye}"/>
      <ellipse cx="33" cy="46" rx="1.5" ry="2" fill="white"/>
      <ellipse cx="63" cy="46" rx="1.5" ry="2" fill="white"/>
      <path d="M 38 62 Q 50 74 62 62" stroke="${c.mouth}" stroke-width="3" fill="none" stroke-linecap="round"/>
      <circle cx="24" cy="58" r="6" fill="${c.cheek}" opacity="0.5"/>
      <circle cx="76" cy="58" r="6" fill="${c.cheek}" opacity="0.5"/>
    `,
    worried: `
      <ellipse cx="35" cy="48" rx="5" ry="6" fill="${c.eye}"/>
      <ellipse cx="65" cy="48" rx="5" ry="6" fill="${c.eye}"/>
      <ellipse cx="33" cy="46" rx="2" ry="2.5" fill="white"/>
      <ellipse cx="63" cy="46" rx="2" ry="2.5" fill="white"/>
      <path d="M 30 38 L 40 42" stroke="${c.mouth}" stroke-width="2.5" stroke-linecap="round"/>
      <path d="M 70 38 L 60 42" stroke="${c.mouth}" stroke-width="2.5" stroke-linecap="round"/>
      <path d="M 40 65 Q 50 58 60 65" stroke="${c.mouth}" stroke-width="3" fill="none" stroke-linecap="round"/>
      <circle cx="24" cy="58" r="5" fill="${c.cheek}" opacity="0.4"/>
      <circle cx="76" cy="58" r="5" fill="${c.cheek}" opacity="0.4"/>
    `,
    sad: `
      <ellipse cx="35" cy="48" rx="4" ry="6" fill="${c.eye}"/>
      <ellipse cx="65" cy="48" rx="4" ry="6" fill="${c.eye}"/>
      <ellipse cx="34" cy="46" rx="1.5" ry="2" fill="white"/>
      <ellipse cx="64" cy="46" rx="1.5" ry="2" fill="white"/>
      <path d="M 38 68 Q 50 58 62 68" stroke="${c.mouth}" stroke-width="3" fill="none" stroke-linecap="round"/>
      <path d="M 30 38 L 38 41" stroke="${c.mouth}" stroke-width="2" stroke-linecap="round"/>
      <path d="M 70 38 L 62 41" stroke="${c.mouth}" stroke-width="2" stroke-linecap="round"/>
      <ellipse cx="28" cy="56" rx="3" ry="5" fill="#60A5FA" opacity="0.6"/>
      <ellipse cx="72" cy="56" rx="3" ry="5" fill="#60A5FA" opacity="0.6"/>
    `,
    alert: `
      <ellipse cx="35" cy="48" rx="6" ry="7" fill="${c.eye}"/>
      <ellipse cx="65" cy="48" rx="6" ry="7" fill="${c.eye}"/>
      <ellipse cx="33" cy="46" rx="2.5" ry="3" fill="white"/>
      <ellipse cx="63" cy="46" rx="2.5" ry="3" fill="white"/>
      <ellipse cx="50" cy="66" rx="6" ry="5" fill="${c.mouth}"/>
      <ellipse cx="50" cy="64" rx="4" ry="3" fill="#FF6B6B"/>
      <circle cx="24" cy="58" r="5" fill="${c.cheek}" opacity="0.4"/>
      <circle cx="76" cy="58" r="5" fill="${c.cheek}" opacity="0.4"/>
    `,
    perfect: `
      <path d="M 28 48 Q 35 42 42 48" stroke="${c.eye}" stroke-width="3" fill="none" stroke-linecap="round"/>
      <path d="M 58 48 Q 65 42 72 48" stroke="${c.eye}" stroke-width="3" fill="none" stroke-linecap="round"/>
      <path d="M 38 62 Q 50 76 62 62" stroke="${c.mouth}" stroke-width="3" fill="none" stroke-linecap="round"/>
      <circle cx="24" cy="56" r="7" fill="${c.cheek}" opacity="0.5"/>
      <circle cx="76" cy="56" r="7" fill="${c.cheek}" opacity="0.5"/>
      <text x="50" y="28" text-anchor="middle" font-size="16">⭐</text>
    `
  };

  // Graduation cap (birrete)
  const hat = `
    <rect x="25" y="18" width="50" height="6" rx="2" fill="#2D2235"/>
    <polygon points="50,8 78,22 50,26 22,22" fill="#2D2235"/>
    <line x1="70" y1="20" x2="78" y2="32" stroke="#F59E0B" stroke-width="2"/>
    <circle cx="79" cy="34" r="3" fill="#F59E0B"/>
  `;

  // Book accessory
  const book = `
    <rect x="72" y="70" width="20" height="16" rx="2" fill="#4A1942" transform="rotate(-15 82 78)"/>
    <rect x="74" y="72" width="16" height="12" rx="1" fill="#6B3FA0" transform="rotate(-15 82 78)"/>
    <line x1="82" y1="71" x2="82" y2="87" stroke="#F5F2EC" stroke-width="1.5" transform="rotate(-15 82 78)"/>
  `;

  return `
    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <radialGradient id="bodyGrad_${mood}" cx="40%" cy="35%" r="60%">
          <stop offset="0%" stop-color="${c.body}" stop-opacity="0.9"/>
          <stop offset="100%" stop-color="${c.body}"/>
        </radialGradient>
      </defs>
      ${hat}
      <ellipse cx="50" cy="58" rx="34" ry="32" fill="url(#bodyGrad_${mood})"/>
      <ellipse cx="50" cy="88" rx="22" ry="8" fill="${c.body}" opacity="0.3"/>
      ${faces[mood] || faces.happy}
      ${book}
    </svg>
  `;
}

// ===== MASCOT LOGIC =====
function getMascotState(student) {
  const att = student.attendance;
  const pay = student.payments;
  const attPercent = (att.present / att.total) * 100;

  // Perfect student
  if (attPercent >= 98 && pay.status === 'paid') {
    return {
      mood: 'perfect',
      moodText: '¡Estudiante estrella!',
      message: `¡Increíble ${student.name.split(' ')[0]}! Asistencia perfecta y pagos al día. ¡Sigue así, eres un ejemplo!`,
      cssClass: 'perfect'
    };
  }

  // Overdue payment
  if (pay.status === 'overdue') {
    return {
      mood: 'sad',
      moodText: '¡Pago vencido!',
      message: `${student.name.split(' ')[0]}, tu mensualidad está vencida. ¡Acércate a secretaría para ponerte al día!`,
      cssClass: 'sad'
    };
  }

  // Bad attendance
  if (attPercent < 85) {
    return {
      mood: 'sad',
      moodText: 'Necesitas venir más',
      message: `Has faltado ${att.absent} días. Tu asistencia está en ${attPercent.toFixed(0)}%. ¡No dejes que baje más!`,
      cssClass: 'sad'
    };
  }

  // Payment coming up (within 7 days)
  if (pay.status === 'pending') {
    const daysUntil = Math.ceil((new Date(pay.dueDate) - new Date()) / (1000*60*60*24));
    if (daysUntil <= 7 && daysUntil > 0) {
      return {
        mood: 'alert',
        moodText: `¡Pago en ${daysUntil} días!`,
        message: `Tu mensualidad de $${pay.monthly.toLocaleString('es-CO')} vence el ${formatDate(pay.dueDate)}. ¡No olvides pagar a tiempo!`,
        cssClass: 'alert'
      };
    }
  }

  // Attendance warning (85-92%)
  if (attPercent < 92) {
    return {
      mood: 'worried',
      moodText: 'Cuidado con las faltas',
      message: `Llevas ${att.absent} falta${att.absent > 1 ? 's' : ''}. Tu asistencia está en ${attPercent.toFixed(0)}%. ¡Intenta no faltar más!`,
      cssClass: 'worried'
    };
  }

  // All good
  return {
    mood: 'happy',
    moodText: '¡Todo bien!',
    message: `¡Vas muy bien ${student.name.split(' ')[0]}! ${att.streak} días seguidos asistiendo. ¡Sigue así!`,
    cssClass: 'happy'
  };
}

function formatDate(dateStr) {
  const d = new Date(dateStr + 'T12:00:00');
  const months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
  return `${d.getDate()} de ${months[d.getMonth()]}`;
}

function formatMoney(n) {
  return '$' + n.toLocaleString('es-CO');
}

// ===== RENDER FUNCTIONS =====
function renderHome() {
  const s = currentStudent;
  const mascot = getMascotState(s);
  const att = s.attendance;
  const pay = s.payments;
  const attPercent = ((att.present / att.total) * 100).toFixed(0);

  const paidMonths = pay.paid.length;
  const progressPercent = (paidMonths / pay.total) * 100;
  const daysUntilPay = Math.ceil((new Date(pay.dueDate) - new Date()) / (1000*60*60*24));

  let payAlertHtml = '';
  if (pay.status === 'overdue') {
    payAlertHtml = `
      <div class="alert-card attendance animate-in" style="animation-delay:0.2s">
        <div class="alert-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="alert-content">
          <div class="alert-title">Mensualidad vencida</div>
          <div class="alert-text">Tu pago de ${formatMoney(pay.monthly)} venció. Acércate a secretaría.</div>
          <div class="alert-time">Vencimiento: ${formatDate(pay.dueDate)}</div>
        </div>
      </div>`;
  } else if (pay.status === 'pending' && daysUntilPay <= 7) {
    payAlertHtml = `
      <div class="alert-card payment animate-in" style="animation-delay:0.2s">
        <div class="alert-icon"><i class="fas fa-clock"></i></div>
        <div class="alert-content">
          <div class="alert-title">Pago próximo en ${daysUntilPay} día${daysUntilPay !== 1 ? 's' : ''}</div>
          <div class="alert-text">Mensualidad de ${formatMoney(pay.monthly)} vence el ${formatDate(pay.dueDate)}</div>
        </div>
      </div>`;
  }

  let attAlertHtml = '';
  if (att.absent >= 5) {
    attAlertHtml = `
      <div class="alert-card attendance animate-in" style="animation-delay:0.3s">
        <div class="alert-icon"><i class="fas fa-user-times"></i></div>
        <div class="alert-content">
          <div class="alert-title">Alerta de asistencia</div>
          <div class="alert-text">Llevas ${att.absent} inasistencias. Máximo permitido: 10.</div>
        </div>
      </div>`;
  }

  document.getElementById('page-home').innerHTML = `
    <div class="app-header">
      <div class="header-top">
        <div>
          <div class="header-greeting">${getGreeting()}</div>
          <div class="header-name">${s.name.split(' ')[0]} 👋</div>
        </div>
        <div class="header-avatar" onclick="navigateTo('profile')">
          <i class="fas fa-user"></i>
        </div>
      </div>
      <div class="header-program">
        <i class="fas fa-graduation-cap"></i> ${s.program} · ${s.semester}
      </div>
    </div>

    <div class="mascot-card animate-in">
      <div class="mascot-container ${mascot.cssClass}">
        ${getMascotSVG(mascot.mood)}
      </div>
      <div class="mascot-info">
        <div class="mascot-mood">${mascot.moodText}</div>
        <div class="mascot-message">${mascot.message}</div>
        ${att.streak > 0 ? `<div class="mascot-streak"><i class="fas fa-fire"></i> ${att.streak} días seguidos</div>` : ''}
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-card animate-in" style="animation-delay:0.1s" onclick="navigateTo('attendance')">
        <div class="stat-icon ${parseInt(attPercent) >= 90 ? 'green' : 'red'}">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value">${attPercent}%</div>
        <div class="stat-label">Asistencia</div>
      </div>
      <div class="stat-card animate-in" style="animation-delay:0.15s" onclick="navigateTo('payments')">
        <div class="stat-icon ${pay.status === 'overdue' ? 'red' : pay.status === 'pending' ? 'orange' : 'green'}">
          <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-value">${paidMonths}/${pay.total}</div>
        <div class="stat-label">Meses pagos</div>
      </div>
      <div class="stat-card animate-in" style="animation-delay:0.2s">
        <div class="stat-icon purple">
          <i class="fas fa-star"></i>
        </div>
        <div class="stat-value">${s.grades.average}</div>
        <div class="stat-label">Promedio</div>
      </div>
    </div>

    ${payAlertHtml || attAlertHtml ? `
    <div class="section">
      <div class="section-header">
        <div class="section-title">Alertas</div>
      </div>
      ${payAlertHtml}
      ${attAlertHtml}
    </div>` : `
    <div class="section">
      <div class="alert-card success animate-in" style="animation-delay:0.25s">
        <div class="alert-icon"><i class="fas fa-check-circle"></i></div>
        <div class="alert-content">
          <div class="alert-title">¡Todo en orden!</div>
          <div class="alert-text">No tienes alertas pendientes. ¡Sigue así!</div>
        </div>
      </div>
    </div>`}

    <div class="section">
      <div class="section-header">
        <div class="section-title">Horario de hoy</div>
        <a class="section-link" onclick="navigateTo('attendance')">Ver todo</a>
      </div>
      ${s.schedule.map((cls, i) => `
        <div class="schedule-item animate-in" style="animation-delay:${0.3 + i * 0.05}s">
          <div class="schedule-time">
            <div class="schedule-hour">${cls.time}</div>
            <div class="schedule-period">${cls.period}</div>
          </div>
          <div class="schedule-divider"></div>
          <div class="schedule-info">
            <div class="schedule-subject">${cls.subject}</div>
            <div class="schedule-teacher">${cls.teacher}</div>
          </div>
          <div class="schedule-room">${cls.room}</div>
        </div>
      `).join('')}
    </div>

    <div class="install-banner" id="installBanner" style="display:none" onclick="installApp()">
      <i class="fas fa-download"></i>
      <div class="install-banner-text">
        <div class="install-banner-title">Instalar en tu celular</div>
        <div class="install-banner-sub">Accede más rápido desde tu pantalla de inicio</div>
      </div>
      <i class="fas fa-chevron-right"></i>
    </div>
  `;
}

function renderAttendance() {
  const s = currentStudent;
  const att = s.attendance;
  const attPercent = ((att.present / att.total) * 100).toFixed(0);
  const dayNames = ['D', 'L', 'M', 'Mi', 'J', 'V', 'S'];

  document.getElementById('page-attendance').innerHTML = `
    <div class="page-header">
      <div class="page-title">Asistencia</div>
    </div>

    <div class="section">
      <div class="attendance-summary">
        <div class="att-summary-card good animate-in">
          <div class="att-summary-value">${att.present}</div>
          <div class="att-summary-label">Días asistidos</div>
        </div>
        <div class="att-summary-card bad animate-in" style="animation-delay:0.1s">
          <div class="att-summary-value">${att.absent}</div>
          <div class="att-summary-label">Inasistencias</div>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="attendance-calendar animate-in" style="animation-delay:0.15s">
        <div class="cal-header">
          <div class="cal-month">Marzo 2026</div>
        </div>
        <div class="cal-days">
          ${dayNames.map(d => `<div class="cal-day-name">${d}</div>`).join('')}
          ${s.calendarDays.map(d => {
            if (d.status === 'empty') return '<div class="cal-day empty">·</div>';
            let cls = '';
            if (d.status === 'present') cls = 'present';
            else if (d.status === 'absent') cls = 'absent';
            else if (d.day === 19) cls = 'today';
            return `<div class="cal-day ${cls}">${d.day}</div>`;
          }).join('')}
        </div>
        <div class="cal-legend">
          <div class="cal-legend-item"><div class="cal-legend-dot green"></div> Asistió</div>
          <div class="cal-legend-item"><div class="cal-legend-dot red"></div> Faltó</div>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-header">
        <div class="section-title">Detalle</div>
      </div>
      <div class="alert-card ${parseInt(attPercent) >= 90 ? 'success' : parseInt(attPercent) >= 80 ? 'payment' : 'attendance'}">
        <div class="alert-icon">
          <i class="fas fa-chart-pie"></i>
        </div>
        <div class="alert-content">
          <div class="alert-title">Asistencia: ${attPercent}%</div>
          <div class="alert-text">
            ${parseInt(attPercent) >= 90
              ? '¡Excelente! Tu asistencia es sobresaliente.'
              : parseInt(attPercent) >= 80
                ? 'Tu asistencia es aceptable, pero intenta mejorar.'
                : '¡Cuidado! Tu asistencia está por debajo del mínimo recomendado.'}
          </div>
          <div class="alert-text" style="margin-top:4px">
            Racha actual: <strong>${att.streak} días seguidos</strong>
          </div>
        </div>
      </div>
    </div>
  `;
}

function renderPayments() {
  const s = currentStudent;
  const pay = s.payments;
  const paidCount = pay.paid.length;
  const progressPercent = (paidCount / pay.total) * 100;
  const progressClass = pay.status === 'overdue' ? 'danger' : pay.status === 'pending' ? 'warning' : 'good';
  const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

  document.getElementById('page-payments').innerHTML = `
    <div class="page-header">
      <div class="page-title">Pagos</div>
    </div>

    <div class="section">
      <div class="payment-card animate-in">
        <div class="payment-header">
          <div>
            <div style="font-size:0.8rem;color:var(--text-light);margin-bottom:2px">Mensualidad</div>
            <div class="payment-amount">${formatMoney(pay.monthly)}</div>
          </div>
          <div class="payment-status ${pay.status}">${
            pay.status === 'paid' ? 'Al día' : pay.status === 'pending' ? 'Pendiente' : 'Vencido'
          }</div>
        </div>
        <div class="payment-progress-bar">
          <div class="payment-progress-fill ${progressClass}" style="width:${progressPercent}%"></div>
        </div>
        <div class="payment-detail">
          <span>${paidCount} de ${pay.total} meses pagados</span>
          <span>Vence: ${formatDate(pay.dueDate)}</span>
        </div>
        <div class="payment-months">
          ${monthNames.map((m, i) => {
            const monthNum = i + 1;
            let cls = 'upcoming';
            if (pay.paid.includes(monthNum)) cls = 'paid';
            else if (monthNum === pay.current) cls = 'current';
            return `<div class="payment-month ${cls}">${m}</div>`;
          }).join('')}
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-header">
        <div class="section-title">Historial</div>
      </div>
      ${pay.paid.map((m, i) => `
        <div class="schedule-item animate-in" style="animation-delay:${0.1 + i * 0.05}s">
          <div class="schedule-time">
            <div class="schedule-hour" style="color:var(--green)">${monthNames[m-1]}</div>
            <div class="schedule-period">2026</div>
          </div>
          <div class="schedule-divider" style="background:var(--green)"></div>
          <div class="schedule-info">
            <div class="schedule-subject">Mensualidad ${monthNames[m-1]}</div>
            <div class="schedule-teacher">${formatMoney(pay.monthly)}</div>
          </div>
          <div style="color:var(--green);font-size:0.85rem"><i class="fas fa-check-circle"></i></div>
        </div>
      `).join('')}

      ${pay.status !== 'paid' ? `
        <div class="schedule-item animate-in" style="animation-delay:0.3s">
          <div class="schedule-time">
            <div class="schedule-hour" style="color:${pay.status === 'overdue' ? 'var(--red)' : 'var(--orange)'}">${monthNames[pay.current-1]}</div>
            <div class="schedule-period">2026</div>
          </div>
          <div class="schedule-divider" style="background:${pay.status === 'overdue' ? 'var(--red)' : 'var(--orange)'}"></div>
          <div class="schedule-info">
            <div class="schedule-subject">Mensualidad ${monthNames[pay.current-1]}</div>
            <div class="schedule-teacher">${formatMoney(pay.monthly)} · Vence ${formatDate(pay.dueDate)}</div>
          </div>
          <div style="color:${pay.status === 'overdue' ? 'var(--red)' : 'var(--orange)'};font-size:0.85rem">
            <i class="fas fa-${pay.status === 'overdue' ? 'exclamation-circle' : 'clock'}"></i>
          </div>
        </div>
      ` : ''}
    </div>
  `;
}

function renderNotifications() {
  const s = currentStudent;
  const mascot = getMascotState(s);

  const notifications = [
    {
      icon: 'fas fa-graduation-cap',
      iconBg: 'var(--green-pale)',
      iconColor: 'var(--green)',
      title: 'Ceremonia de graduación 2025',
      text: 'Se acerca la ceremonia de graduación. ¡No te la pierdas!',
      time: 'Hoy',
      unread: true
    },
    {
      icon: 'fas fa-bullhorn',
      iconBg: 'var(--purple-pale)',
      iconColor: 'var(--purple-mid)',
      title: 'Inscripciones abiertas 2026',
      text: 'Nuevos programas disponibles. Comparte con tus amigos.',
      time: 'Ayer',
      unread: true
    },
    {
      icon: 'fas fa-calendar-check',
      iconBg: 'var(--blue-light)',
      iconColor: 'var(--blue)',
      title: 'Taller de emprendimiento',
      text: 'Este viernes: taller de emprendimiento estudiantil. ¡Inscríbete!',
      time: 'Hace 2 días',
      unread: false
    },
    {
      icon: 'fas fa-heart',
      iconBg: 'var(--red-light)',
      iconColor: 'var(--red)',
      title: 'Proyección Social',
      text: 'Jornada de proyección social este sábado. ¡Participa!',
      time: 'Hace 3 días',
      unread: false
    }
  ];

  // Add dynamic notification based on mascot state
  if (mascot.mood === 'sad' || mascot.mood === 'alert') {
    notifications.unshift({
      icon: 'fas fa-exclamation-triangle',
      iconBg: 'var(--orange-light)',
      iconColor: 'var(--orange)',
      title: mascot.moodText,
      text: mascot.message,
      time: 'Ahora',
      unread: true
    });
  }

  document.getElementById('page-notifications').innerHTML = `
    <div class="page-header">
      <div class="page-title">Notificaciones</div>
    </div>
    <div class="section">
      ${notifications.map((n, i) => `
        <div class="notif-item ${n.unread ? 'unread' : ''} animate-in" style="animation-delay:${i * 0.08}s">
          <div class="notif-dot" style="background:${n.iconBg};color:${n.iconColor}">
            <i class="${n.icon}"></i>
          </div>
          <div class="notif-content">
            <div class="notif-title">${n.title}</div>
            <div class="notif-text">${n.text}</div>
            <div class="notif-time">${n.time}</div>
          </div>
        </div>
      `).join('')}
    </div>
  `;
}

function renderProfile() {
  const s = currentStudent;

  document.getElementById('page-profile').innerHTML = `
    <div class="profile-header">
      <div class="profile-avatar"><i class="fas fa-user-graduate"></i></div>
      <div class="profile-name">${s.name}</div>
      <div class="profile-id">Código: ${Object.keys(STUDENTS).find(k => STUDENTS[k] === s)}</div>
      <div class="profile-program"><i class="fas fa-graduation-cap"></i> ${s.program} · ${s.semester}</div>
    </div>

    <div class="profile-section animate-in">
      <div class="profile-item">
        <i class="fas fa-chart-line" style="background:var(--green-pale);color:var(--green)"></i>
        <div class="profile-item-text">
          <div class="profile-item-label">Promedio general</div>
          <div class="profile-item-value">${s.grades.average} / 5.0</div>
        </div>
        <i class="fas fa-chevron-right"></i>
      </div>
      <div class="profile-item">
        <i class="fas fa-trophy" style="background:var(--orange-light);color:var(--orange)"></i>
        <div class="profile-item-text">
          <div class="profile-item-label">Mejor materia</div>
          <div class="profile-item-value">${s.grades.best}</div>
        </div>
        <i class="fas fa-chevron-right"></i>
      </div>
      <div class="profile-item">
        <i class="fas fa-book" style="background:var(--purple-pale);color:var(--purple-mid)"></i>
        <div class="profile-item-text">
          <div class="profile-item-label">Para mejorar</div>
          <div class="profile-item-value">${s.grades.worst}</div>
        </div>
        <i class="fas fa-chevron-right"></i>
      </div>
    </div>

    <div class="profile-section animate-in" style="animation-delay:0.1s;margin-top:16px">
      <div class="profile-item">
        <i class="fas fa-bell" style="background:var(--blue-light);color:var(--blue)"></i>
        <div class="profile-item-text">
          <div class="profile-item-label">Notificaciones</div>
          <div class="profile-item-value">Activadas</div>
        </div>
        <i class="fas fa-chevron-right"></i>
      </div>
      <div class="profile-item">
        <i class="fas fa-shield-alt" style="background:var(--green-pale);color:var(--green)"></i>
        <div class="profile-item-text">
          <div class="profile-item-label">Cambiar PIN</div>
          <div class="profile-item-value">****</div>
        </div>
        <i class="fas fa-chevron-right"></i>
      </div>
      <div class="profile-item">
        <i class="fas fa-info-circle" style="background:var(--sand);color:var(--text-mid)"></i>
        <div class="profile-item-text">
          <div class="profile-item-label">Acerca de INTEP</div>
          <div class="profile-item-value">v1.0.0</div>
        </div>
        <i class="fas fa-chevron-right"></i>
      </div>
    </div>

    <button class="btn-logout" onclick="logout()" style="margin-top:16px">
      <i class="fas fa-sign-out-alt"></i> Cerrar sesión
    </button>
  `;
}

// ===== NAVIGATION =====
function navigateTo(page) {
  currentPage = page;
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.getElementById(`page-${page}`).classList.add('active');

  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.querySelector(`[data-page="${page}"]`)?.classList.add('active');

  switch (page) {
    case 'home': renderHome(); break;
    case 'attendance': renderAttendance(); break;
    case 'payments': renderPayments(); break;
    case 'notifications': renderNotifications(); break;
    case 'profile': renderProfile(); break;
  }

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ===== HELPERS =====
function getGreeting() {
  const h = new Date().getHours();
  if (h < 12) return 'Buenos días';
  if (h < 18) return 'Buenas tardes';
  return 'Buenas noches';
}

function showToast(msg) {
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3000);
}

// ===== LOGIN =====
function handleLogin(e) {
  e.preventDefault();
  const code = document.getElementById('studentCode').value.trim();
  const pin = document.getElementById('studentPin').value.trim();

  const student = STUDENTS[code];
  if (!student) {
    showToast('Código de estudiante no encontrado');
    return;
  }
  if (student.pin !== pin) {
    showToast('PIN incorrecto');
    return;
  }

  currentStudent = student;
  localStorage.setItem('intep_student', code);

  document.querySelector('.login-screen').classList.add('hidden');
  document.querySelector('.app').classList.add('active');

  navigateTo('home');
  showToast(`¡Bienvenid@ ${student.name.split(' ')[0]}!`);
}

function logout() {
  currentStudent = null;
  localStorage.removeItem('intep_student');
  document.querySelector('.app').classList.remove('active');
  document.querySelector('.login-screen').classList.remove('hidden');
  document.getElementById('studentCode').value = '';
  document.getElementById('studentPin').value = '';
}

// ===== PWA INSTALL =====
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  const banner = document.getElementById('installBanner');
  if (banner) banner.style.display = 'flex';
});

function installApp() {
  if (deferredPrompt) {
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(r => {
      if (r.outcome === 'accepted') showToast('¡App instalada!');
      deferredPrompt = null;
      document.getElementById('installBanner').style.display = 'none';
    });
  }
}

// ===== INIT =====
window.addEventListener('DOMContentLoaded', () => {
  // Splash screen
  setTimeout(() => {
    document.querySelector('.splash').classList.add('hide');
  }, 1500);

  // Auto-login if saved
  setTimeout(() => {
    const saved = localStorage.getItem('intep_student');
    if (saved && STUDENTS[saved]) {
      currentStudent = STUDENTS[saved];
      document.querySelector('.login-screen').classList.add('hidden');
      document.querySelector('.app').classList.add('active');
      navigateTo('home');
    }
  }, 1600);

  // Login form
  document.getElementById('loginForm').addEventListener('submit', handleLogin);

  // Bottom nav
  document.querySelectorAll('.nav-item').forEach(btn => {
    btn.addEventListener('click', () => navigateTo(btn.dataset.page));
  });

  // Register SW
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./sw.js');
  }
});
