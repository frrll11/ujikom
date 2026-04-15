// ============================================================
// admin.js - JavaScript untuk halaman Admin Dashboard
// Fitur: Drag & Drop upload, File Preview Modal
// ============================================================

/**
 * formatBytes(bytes) — Konversi ukuran file dari bytes
 * ke format manusiawi: KB atau MB.
 */
function formatBytes(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
}

/**
 * previewFile(input) — Menampilkan info file yang dipilih
 * (nama, ukuran, ikon emoji sesuai tipe) dan menyembunyikan placeholder.
 */
function previewFile(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const ext = file.name.split('.').pop().toLowerCase();

    // Peta ekstensi ke ikon emoji dan warna
    const icons = {
        pdf: { icon: '📄', color: '#c53030' },
        doc: { icon: '📝', color: '#2b6cb0' },
        docx: { icon: '📝', color: '#2b6cb0' },
        xls: { icon: '📊', color: '#276749' },
        xlsx: { icon: '📊', color: '#276749' },
    };
    const info = icons[ext] || { icon: '📎', color: '#718096' };

    // Isi elemen preview dengan data file
    document.getElementById('previewIcon').textContent = info.icon;
    document.getElementById('previewName').textContent = file.name;
    document.getElementById('previewSize').textContent = formatBytes(file.size);

    // Sembunyikan placeholder, tampilkan preview
    const placeholder = document.getElementById('uploadPlaceholder');
    const filePreview = document.getElementById('filePreview');
    placeholder.style.display = 'none';
    filePreview.classList.add('show');
}

/**
 * removeFile() — Membatalkan pilihan file:
 * reset input, sembunyikan preview, tampilkan kembali placeholder.
 */
function removeFile() {
    const fileInput = document.getElementById('fileInput');
    const filePreview = document.getElementById('filePreview');
    const placeholder = document.getElementById('uploadPlaceholder');
    
    fileInput.value = '';
    filePreview.classList.remove('show');
    placeholder.style.display = 'block';
}

/**
 * previewFileModal(filePath, fileExt, fileName) — Menampilkan modal preview file
 * Mendukung PDF, Word, Excel menggunakan Google Docs Viewer
 */
function previewFileModal(filePath, fileExt, fileName) {
    const modal = document.getElementById('fileModal');
    const modalBody = document.getElementById('modalBody');
    const modalFileName = document.getElementById('modalFileName');
    
    modalFileName.textContent = fileName;
    
    // Tentukan URL preview berdasarkan ekstensi file
    let previewHTML = '';
    
    if (fileExt === 'pdf') {
        // Untuk PDF, gunakan embed langsung
        previewHTML = `<iframe src="${filePath}#toolbar=1" width="100%" height="100%"></iframe>`;
    } else if (['doc', 'docx', 'xls', 'xlsx'].includes(fileExt)) {
        // Untuk Word dan Excel, gunakan Google Docs Viewer
        const encodedUrl = encodeURIComponent(window.location.origin + '/' + filePath);
        previewHTML = `
            <div class="office-preview">
                <iframe src="https://docs.google.com/gview?url=${encodedUrl}&embedded=true" 
                        width="100%" height="100%" style="border: none;">
                </iframe>
                <div class="download-link">
                    <a href="${filePath}" download="${fileName}">
                        <i class="fas fa-download"></i> Download file untuk melihat dengan aplikasi Office
                    </a>
                </div>
            </div>
        `;
    } else {
        // Untuk file lain, tampilkan link download
        previewHTML = `
            <div class="office-preview" style="text-align: center; padding: 50px;">
                <i class="fas fa-file" style="font-size: 64px; color: #718096; margin-bottom: 20px;"></i>
                <p>File tidak dapat ditampilkan secara langsung.</p>
                <div class="download-link">
                    <a href="${filePath}" download="${fileName}" class="btn-primary" style="display: inline-block; margin-top: 20px;">
                        <i class="fas fa-download"></i> Download File
                    </a>
                </div>
            </div>
        `;
    }
    
    modalBody.innerHTML = previewHTML;
    modal.style.display = 'block';
}

/**
 * closeModal() — Menutup modal preview
 */
function closeModal() {
    const modal = document.getElementById('fileModal');
    modal.style.display = 'none';
    document.getElementById('modalBody').innerHTML = '';
}

/**
 * initDragAndDrop() — Inisialisasi fitur drag and drop untuk upload file
 */
function initDragAndDrop() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');
    
    if (!uploadArea) return;
    
    // Highlight area upload saat file di-drag masuk
    ['dragenter', 'dragover'].forEach(e => {
        uploadArea.addEventListener(e, ev => {
            ev.preventDefault();
            uploadArea.classList.add('dragover');
        });
    });
    
    // Hilangkan highlight saat file di-drag keluar atau di-drop
    ['dragleave', 'drop'].forEach(e => {
        uploadArea.addEventListener(e, ev => {
            ev.preventDefault();
            uploadArea.classList.remove('dragover');
        });
    });
    
    // Tangani file yang di-drop ke area upload
    uploadArea.addEventListener('drop', ev => {
        const files = ev.dataTransfer.files;
        if (files.length) {
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            fileInput.files = dt.files;
            previewFile(fileInput);
        }
    });
}

/**
 * initModalEvents() — Inisialisasi event untuk modal
 */
function initModalEvents() {
    // Tutup modal jika klik di luar area modal
    window.onclick = function(event) {
        const modal = document.getElementById('fileModal');
        if (event.target == modal) {
            closeModal();
        }
    };
    
    // Tutup modal dengan tombol ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
}

/**
 * initPage() — Inisialisasi semua fungsi saat halaman dimuat
 */
function initPage() {
    initDragAndDrop();
    initModalEvents();
}

// Jalankan inisialisasi saat halaman selesai dimuat
document.addEventListener('DOMContentLoaded', initPage);