// ============================================================
//  FEN-X Frizerski Salon -- Booking JavaScript
//  Verzija za klasično HTML stran (Povezano s fenx_admin.php)
// ============================================================

// Povežemo neposredno na PHP skripto, ki bo delovala kot naš API

const API_BASE = "fenx_admin.php";
// ---- Stanje aplikacije ----
let selectedDate = null;
let selectedTime = null;
let currentDate = new Date();
let lightboxImages = [];
let lightboxIndex = 0;

const MONTHS_SL = ["JANUAR","FEBRUAR","MAREC","APRIL","MAJ","JUNIJ","JULIJ","AVGUST","SEPTEMBER","OKTOBER","NOVEMBER","DECEMBER"];
const TIME_SLOTS = ["08:00","09:00","10:00","11:00","13:00","14:00","15:00","16:00"];

// ---- Inicializacija ----
document.addEventListener("DOMContentLoaded", () => {
  renderCalendar();
  setupForm();
  setupLightbox();
});

// ---- Koledar ----
function renderCalendar() {
  const headerEl = document.getElementById("cal-header");
  const gridEl   = document.getElementById("cal-grid");
  if (!headerEl || !gridEl) return;

  headerEl.textContent = `${MONTHS_SL[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
  gridEl.innerHTML = "";

  // Prazni dnevi pred prvim
  const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1).getDay();
  const offset = firstDay === 0 ? 6 : firstDay - 1;
  for (let i = 0; i < offset; i++) {
    const el = document.createElement("div");
    el.className = "cal-day empty";
    gridEl.appendChild(el);
  }

  // Dnevi v mesecu
  const daysInMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0).getDate();
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  for (let d = 1; d <= daysInMonth; d++) {
    const date = new Date(currentDate.getFullYear(), currentDate.getMonth(), d);
    const el   = document.createElement("div");
    const dateStr = formatDate(date);

    el.className  = "cal-day";
    el.textContent = d;

    if (date < today) {
      el.classList.add("disabled");
    } else {
      el.addEventListener("click", () => selectDate(dateStr, el));
      if (selectedDate === dateStr) el.classList.add("selected");
    }

    gridEl.appendChild(el);
  }
}

function selectDate(dateStr, el) {
  selectedDate = dateStr;
  selectedTime = null;
  document.querySelectorAll(".cal-day.selected").forEach(e => e.classList.remove("selected"));
  el.classList.add("selected");
  loadTimeSlots();
}

async function loadTimeSlots() {
  const container = document.getElementById("time-slots");
  if (!container) return;
  container.innerHTML = "<p>Nalagam urice...</p>";

  try {
    // Zahtevek pošljemo na fenx_admin.php s parametrom 'api_action'
    const res  = await fetch(`${API_BASE}?api_action=get_zasedene&datum=${selectedDate}`);
    if (!res.ok) throw new Error("Napaka pri branju strežnika");

    const data = await res.json();
    const zasedene = data.zasedeneUre || [];

    container.innerHTML = "";
    TIME_SLOTS.forEach(slot => {
      const btn = document.createElement("button");
      btn.className  = "time-btn" + (zasedene.includes(slot) ? " taken" : "");
      btn.textContent = slot;
      btn.disabled   = zasedene.includes(slot);
      btn.addEventListener("click", () => {
        selectedTime = slot;
        document.querySelectorAll(".time-btn.selected").forEach(b => b.classList.remove("selected"));
        btn.classList.add("selected");
      });
      container.appendChild(btn);
    });
  } catch (e) {
    console.error(e);
    container.innerHTML = "<p>Napaka pri nalaganju.</p>";
  }
}

function prevMonth() {
  currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
  renderCalendar();
}

function nextMonth() {
  currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1);
  renderCalendar();
}

// ---- Obrazec ----
function setupForm() {
  const form = document.getElementById("booking-form");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!selectedDate || !selectedTime) {
      alert("Prosimo izberite datum in uro.");
      return;
    }

    const payload = {
      api_action: "add_termin",
      ime:      form.ime.value.trim(),
      spol:     form.spol.value,
      storitev: form.storitev.value,
      opomba:   form.opomba.value.trim() || null,
      datum:    selectedDate,
      ura:      selectedTime,
    };

    const btn = form.querySelector("[type=submit]");
    btn.disabled = true;
    btn.textContent = "PRIJAVA...";

    try {
      const res = await fetch(API_BASE, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify(payload),
      });

      const data = await res.json();

      if (res.status === 409 || (data && data.error === "conflict")) {
        alert("Ta termin je že zaseden. Prosimo izberite drugega.");
        return;
      }
      if (!res.ok || (data && data.status !== "success")) throw new Error("Napaka strežnika");

      alert(`✓ Termin potrjen!\n${payload.ime} — ${payload.datum} ob ${payload.ura}`);
      form.reset();
      selectedDate = null;
      selectedTime = null;
      renderCalendar();
      const slotsEl = document.getElementById("time-slots");
      if (slotsEl) slotsEl.innerHTML = "";
    } catch (err) {
      alert("Prišlo je do napake pri shranjevanju. Prosimo poskusite znova.");
    } finally {
      btn.disabled = false;
      btn.textContent = "POTRDI REZERVACIJO";
    }
  });
}

// ---- Lightbox ----
function setupLightbox() {
  // Pustimo prazno, ker se zdaj izvaja v index.html preko 'custom' funkcij
}

function formatDate(date) {
  return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,"0")}-${String(date.getDate()).padStart(2,"0")}`;
}