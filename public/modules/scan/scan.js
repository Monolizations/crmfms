// /modules/scan/scan.js
const API = "/crmfms/api/attendance/scan.php";
let html5QrCode;
let isScanning = false;

const startBtn = document.getElementById("startBtn");
const stopBtn = document.getElementById("stopBtn");
const resultBox = document.getElementById("scanResult");

startBtn.addEventListener("click", () => {
  if (isScanning) return;
  isScanning = true;
  resultBox.textContent = "Scanning...";
  startBtn.classList.add("d-none");
  stopBtn.classList.remove("d-none");

  html5QrCode = new Html5Qrcode("reader");
  html5QrCode.start(
    { facingMode: "environment" },
    { fps: 10, qrbox: 250 },
    onScanSuccess
  );
});

stopBtn.addEventListener("click", () => {
  stopScanning();
});

async function onScanSuccess(decodedText) {
  stopScanning();
  resultBox.textContent = `Scanned: ${decodedText}`;

  try {
    const res = await fetch(API, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ code_value: decodedText })
    });
    const data = await res.json();
    if (data.success) {
      resultBox.className = "text-success mt-3";
      resultBox.textContent = data.message;
    } else {
      resultBox.className = "text-danger mt-3";
      resultBox.textContent = data.message || "Failed to log attendance";
    }
  } catch (err) {
    console.error(err);
    resultBox.className = "text-danger mt-3";
    resultBox.textContent = "Server error.";
  }
}

function stopScanning() {
  if (html5QrCode && isScanning) {
    html5QrCode.stop().then(() => {
      html5QrCode.clear();
      isScanning = false;
      startBtn.classList.remove("d-none");
      stopBtn.classList.add("d-none");
      resultBox.textContent = "Scanner stopped.";
    });
  }
}
