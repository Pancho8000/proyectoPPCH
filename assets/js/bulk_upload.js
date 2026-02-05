document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const filesTable = document.getElementById('files-table');
    const filesTableBody = document.getElementById('files-table-body');
    const analysisProgressContainer = document.getElementById('analysis-progress-container');
    const analysisBar = document.getElementById('analysis-bar');
    const btnProcessAll = document.getElementById('btn-process-all');

    let fileQueue = []; // { file: File, status: 'pending'|'analyzed'|'error', analysis: {} }

    // Drop Zone Events
    dropZone.addEventListener('click', () => fileInput.click());
    
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.backgroundColor = '#e3f2fd';
    });
    
    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.style.backgroundColor = '#f8f9fa';
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.backgroundColor = '#f8f9fa';
        handleFiles(e.dataTransfer.files);
    });
    
    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    function handleFiles(files) {
        if (files.length === 0) return;
        
        // Add to queue
        Array.from(files).forEach(file => {
            if (file.type === 'application/pdf') {
                const id = 'f_' + Math.random().toString(36).substr(2, 9);
                fileQueue.push({
                    id: id,
                    file: file,
                    status: 'pending',
                    analysis: null,
                    userSelection: {
                        entityType: '', // worker | vehicle
                        entityId: '',
                        docType: '',
                        date: ''
                    }
                });
            }
        });

        renderTable();
        processQueue();
    }

    function renderTable() {
        if (fileQueue.length > 0) {
            filesTable.style.display = 'table';
            btnProcessAll.disabled = false;
        } else {
            filesTable.style.display = 'none';
            btnProcessAll.disabled = true;
        }

        filesTableBody.innerHTML = '';
        
        fileQueue.forEach(item => {
            const tr = document.createElement('tr');
            tr.dataset.id = item.id;
            
            // File Name
            const tdName = document.createElement('td');
            tdName.innerHTML = `<i class="fas fa-file-pdf text-danger me-2"></i>${item.file.name}`;
            tr.appendChild(tdName);

            // Entity Select
            const tdEntity = document.createElement('td');
            tdEntity.appendChild(createEntitySelector(item));
            tr.appendChild(tdEntity);

            // Doc Type Select
            const tdType = document.createElement('td');
            tdType.appendChild(createTypeSelector(item));
            tr.appendChild(tdType);

            // Date Input
            const tdDate = document.createElement('td');
            const dateInput = document.createElement('input');
            dateInput.type = 'date';
            dateInput.className = 'form-control form-control-sm';
            dateInput.value = item.userSelection.date || (item.analysis ? item.analysis.date : '') || '';
            dateInput.onchange = (e) => { item.userSelection.date = e.target.value; updateStatus(item); };
            tdDate.appendChild(dateInput);
            tr.appendChild(tdDate);

            // Status
            const tdStatus = document.createElement('td');
            tdStatus.id = `status-${item.id}`;
            tdStatus.innerHTML = getStatusBadge(item);
            tr.appendChild(tdStatus);

            // Actions
            const tdActions = document.createElement('td');
            const btnDel = document.createElement('button');
            btnDel.className = 'btn btn-sm btn-outline-danger';
            btnDel.innerHTML = '<i class="fas fa-trash"></i>';
            btnDel.onclick = () => {
                fileQueue = fileQueue.filter(f => f.id !== item.id);
                renderTable();
            };
            tdActions.appendChild(btnDel);
            tr.appendChild(tdActions);

            filesTableBody.appendChild(tr);
        });
    }

    function createEntitySelector(item) {
        const wrapper = document.createElement('div');
        
        // Type Switcher (Worker vs Vehicle)
        const typeSelect = document.createElement('select');
        typeSelect.className = 'form-select form-select-sm mb-1';
        typeSelect.innerHTML = `
            <option value="">-- Tipo --</option>
            <option value="worker">Trabajador</option>
            <option value="vehicle">Vehículo</option>
        `;
        typeSelect.value = item.userSelection.entityType;
        
        // Entity Dropdown
        const entitySelect = document.createElement('select');
        entitySelect.className = 'form-select form-select-sm';
        entitySelect.disabled = !item.userSelection.entityType;
        
        populateEntityDropdown(entitySelect, item.userSelection.entityType, item.userSelection.entityId);

        // Events
        typeSelect.onchange = (e) => {
            item.userSelection.entityType = e.target.value;
            item.userSelection.entityId = ''; // Reset ID
            item.userSelection.docType = ''; // Reset Doc Type as lists differ
            populateEntityDropdown(entitySelect, e.target.value, '');
            entitySelect.disabled = !e.target.value;
            renderTable(); // Re-render to update Doc Type dropdown options
        };

        entitySelect.onchange = (e) => {
            item.userSelection.entityId = e.target.value;
            updateStatus(item);
        };

        wrapper.appendChild(typeSelect);
        wrapper.appendChild(entitySelect);
        return wrapper;
    }

    function populateEntityDropdown(select, type, selectedId) {
        select.innerHTML = '<option value="">-- Seleccionar --</option>';
        if (type === 'worker') {
            WORKERS.forEach(w => {
                const opt = document.createElement('option');
                opt.value = w.id;
                opt.text = `${w.nombre} (${w.rut})`;
                if (w.id == selectedId) opt.selected = true;
                select.appendChild(opt);
            });
        } else if (type === 'vehicle') {
            VEHICLES.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.id;
                opt.text = `${v.patente} - ${v.marca} ${v.modelo}`;
                if (v.id == selectedId) opt.selected = true;
                select.appendChild(opt);
            });
        }
    }

    function createTypeSelector(item) {
        const select = document.createElement('select');
        select.className = 'form-select form-select-sm';
        select.innerHTML = '<option value="">-- Tipo Doc --</option>';
        
        let options = [];
        if (item.userSelection.entityType === 'worker') options = WORKER_DOC_TYPES;
        else if (item.userSelection.entityType === 'vehicle') options = VEHICLE_DOC_TYPES;
        
        options.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t;
            opt.text = t;
            if (t === item.userSelection.docType) opt.selected = true;
            select.appendChild(opt);
        });

        select.onchange = (e) => {
            item.userSelection.docType = e.target.value;
            updateStatus(item);
        };
        
        return select;
    }

    function getStatusBadge(item) {
        if (item.status === 'pending') return '<span class="badge bg-secondary">Pendiente</span>';
        if (item.status === 'analyzing') return '<span class="spinner-border spinner-border-sm text-primary"></span>';
        if (item.status === 'error') return '<span class="badge bg-danger">Error</span>';
        
        // Ready check
        if (item.userSelection.entityType && item.userSelection.entityId && item.userSelection.docType) {
            return '<span class="badge bg-success">Listo</span>';
        }
        return '<span class="badge bg-warning text-dark">Incompleto</span>';
    }

    function updateStatus(item) {
        const cell = document.getElementById(`status-${item.id}`);
        if (cell) cell.innerHTML = getStatusBadge(item);
    }

    async function processQueue() {
        const pending = fileQueue.filter(f => f.status === 'pending');
        if (pending.length === 0) return;

        analysisProgressContainer.style.display = 'block';
        
        for (let i = 0; i < pending.length; i++) {
            const item = pending[i];
            item.status = 'analyzing';
            updateStatus(item);
            
            // Update progress
            const percent = Math.round(((i) / pending.length) * 100);
            analysisBar.style.width = `${percent}%`;

            try {
                const formData = new FormData();
                formData.append('doc_archivo', item.file);
                
                const res = await fetch('../scripts/parse_doc_dates.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    item.status = 'analyzed';
                    item.analysis = data;
                    
                    // Auto-fill logic
                    if (data.date) item.userSelection.date = data.date;
                    
                    // Doc Type
                    if (data.doc_type) item.userSelection.docType = data.doc_type;
                    
                    // Entity Logic
                    if (data.doc_category) item.userSelection.entityType = data.doc_category;

                    // Identity Match
                    if (data.doc_category === 'worker' || (!data.doc_category && data.extracted_ruts.length > 0)) {
                        // Default to worker if RUT found
                        if (!item.userSelection.entityType) item.userSelection.entityType = 'worker';
                        
                        // Try to find worker by extracted RUTs
                        if (data.extracted_ruts && data.extracted_ruts.length > 0) {
                            for (let rut of data.extracted_ruts) {
                                const cleanRut = rut.replace(/\./g, '').replace(/-/g, '').toUpperCase();
                                // WORKERS.rut might have dots/dash
                                const found = WORKERS.find(w => w.rut.replace(/\./g, '').replace(/-/g, '').toUpperCase().includes(cleanRut));
                                if (found) {
                                    item.userSelection.entityId = found.id;
                                    break;
                                }
                            }
                        }
                    } 
                    
                    if (data.doc_category === 'vehicle' || (!data.doc_category && data.extracted_patentes.length > 0)) {
                         if (!item.userSelection.entityType) item.userSelection.entityType = 'vehicle';
                         
                         if (data.extracted_patentes && data.extracted_patentes.length > 0) {
                             for (let pat of data.extracted_patentes) {
                                 const cleanPat = pat.replace(/[^A-Z0-9]/g, '');
                                 const found = VEHICLES.find(v => v.patente.replace(/[^A-Z0-9]/g, '').includes(cleanPat));
                                 if (found) {
                                     item.userSelection.entityId = found.id;
                                     break;
                                 }
                             }
                         }
                    }
                    
                    // If category not found but we have type
                    if (!item.userSelection.entityType && data.doc_type) {
                        if (WORKER_DOC_TYPES.includes(data.doc_type)) item.userSelection.entityType = 'worker';
                        else if (VEHICLE_DOC_TYPES.includes(data.doc_type)) item.userSelection.entityType = 'vehicle';
                    }

                } else {
                    item.status = 'error';
                }
            } catch (e) {
                console.error(e);
                item.status = 'error';
            }
            
            // Re-render to show auto-filled data
            renderTable();
        }
        
        analysisBar.style.width = '100%';
        setTimeout(() => { analysisProgressContainer.style.display = 'none'; }, 1000);
    }
    
    // Save All
    btnProcessAll.onclick = async () => {
        const readyFiles = fileQueue.filter(f => f.userSelection.entityType && f.userSelection.entityId && f.userSelection.docType);
        if (readyFiles.length === 0) {
            alert('No hay archivos listos para guardar. Verifique que tengan Entidad y Tipo seleccionados.');
            return;
        }
        
        if (!confirm(`Se guardarán ${readyFiles.length} documentos. ¿Continuar?`)) return;
        
        btnProcessAll.disabled = true;
        const originalText = btnProcessAll.innerHTML;
        btnProcessAll.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        
        for (let item of readyFiles) {
             const formData = new FormData();
             formData.append('doc_archivo', item.file);
             formData.append('entity_type', item.userSelection.entityType);
             formData.append('entity_id', item.userSelection.entityId);
             formData.append('doc_type', item.userSelection.docType);
             formData.append('doc_date', item.userSelection.date);
             
             try {
                 const res = await fetch('../scripts/process_bulk_upload.php', {
                     method: 'POST',
                     body: formData
                 });
                 const result = await res.json();
                 if (result.success) {
                     // Remove from queue
                     fileQueue = fileQueue.filter(f => f.id !== item.id);
                 } else {
                     // Keep in queue, show error
                     console.error(result.message);
                 }
             } catch (e) {
                 console.error('Network error', e);
             }
        }
        
        renderTable();
        btnProcessAll.disabled = false;
        btnProcessAll.innerHTML = originalText;
        
        if (fileQueue.length === 0) {
            alert('Todos los archivos se guardaron correctamente.');
        } else {
            alert('Algunos archivos no pudieron guardarse. Revise la lista.');
        }
    };
});