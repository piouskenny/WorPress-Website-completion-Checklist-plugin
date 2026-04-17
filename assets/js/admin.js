(function() {
    const { root, nonce } = qaChecklistData;

    const state = {
        view: 'dashboard', // or 'detail'
        projects: [],
        users: [],
        currentProject: null,
        loading: false
    };

    const api = {
        async fetch(endpoint, options = {}) {
            const response = await fetch(`${root}qa-checklist/v1${endpoint}`, {
                ...options,
                headers: {
                    'X-WP-Nonce': nonce,
                    'Content-Type': 'application/json',
                    ...options.headers
                }
            });
            return response.json();
        },
        getProjects: () => api.fetch('/projects'),
        getProject: (id) => api.fetch(`/projects/${id}`),
        createProject: (data) => api.fetch('/projects', { method: 'POST', body: JSON.stringify(data) }),
        updateProject: (id, data) => api.fetch(`/projects/${id}`, { method: 'PATCH', body: JSON.stringify(data) }),
        updateItem: (id, data) => api.fetch(`/checklist/${id}`, { method: 'PATCH', body: JSON.stringify(data) }),
        getUsers: () => api.fetch('/users'),
        runAudit: (id) => api.fetch(`/projects/${id}/audit`, { method: 'POST' })
    };

    const ui = {
        app: document.getElementById('qa-checklist-app'),
        dashboard: document.getElementById('dashboard-view'),
        detail: document.getElementById('project-detail-view'),
        projectContent: document.getElementById('project-content'),
        projectsGrid: document.getElementById('projects-grid'),
        newProjectModal: document.getElementById('new-project-modal'),
        userSelect: document.getElementById('user-select'),
        
        statusColors: {
            'NOT_STARTED': 'bg-slate-100 text-slate-600',
            'IN_PROGRESS': 'bg-blue-100 text-blue-600',
            'IN_QA': 'bg-amber-100 text-amber-600',
            'FAILED': 'bg-red-100 text-red-600',
            'COMPLETED': 'bg-emerald-100 text-emerald-600'
        }
    };

    async function init() {
        setupEventListeners();
        await loadDashboard();
        await loadUsers();
    }

    function setupEventListeners() {
        document.getElementById('new-project-btn').addEventListener('click', () => {
            ui.newProjectModal.classList.remove('hidden');
            const nameInput = ui.newProjectModal.querySelector('input[name="name"]');
            if (nameInput) {
                nameInput.value = qaChecklistData.siteName || '';
            }
        });

        document.getElementById('close-modal').addEventListener('click', () => {
            ui.newProjectModal.classList.add('hidden');
        });

        document.getElementById('new-project-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            
            // Map checkboxes to booleans
            ['has_woocommerce', 'has_forms', 'has_seo'].forEach(key => {
                data[key] = !!data[key];
            });

            await api.createProject(data);
            ui.newProjectModal.classList.add('hidden');
            e.target.reset();
            loadDashboard();
        });

        document.getElementById('back-to-dashboard').addEventListener('click', () => {
            switchView('dashboard');
        });
    }

    async function loadUsers() {
        state.users = await api.getUsers();
        ui.userSelect.innerHTML = state.users.map(u => `<option value="${u.ID}">${u.display_name}</option>`).join('');
    }

    async function loadDashboard() {
        ui.projectsGrid.innerHTML = `
            <div class="col-span-full py-12 text-center text-slate-400">Loading projects...</div>
        `;
        
        state.projects = await api.getProjects();
        renderDashboard();
    }

    function switchView(view) {
        state.view = view;
        if (view === 'dashboard') {
            ui.dashboard.classList.remove('hidden');
            ui.detail.classList.add('hidden');
            loadDashboard();
        } else {
            ui.dashboard.classList.add('hidden');
            ui.detail.classList.remove('hidden');
        }
    }

    function renderDashboard() {
        if (state.projects.length === 0) {
            ui.projectsGrid.innerHTML = `
                <div class="col-span-full py-20 flex flex-col items-center justify-center glass-card rounded-3xl border-0 animate-fade-in">
                    <div class="bg-indigo-100 p-4 rounded-full mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-indigo-900 font-bold text-xl mb-1">No Projects Found</p>
                    <p class="text-indigo-800/60 font-medium">Create your first QA pipeline to get started.</p>
                </div>
            `;
            return;
        }

        ui.projectsGrid.innerHTML = state.projects.map((p, index) => {
            const statusClass = ui.statusColors[p.status] || 'bg-slate-100';
            return `
                <div class="glass-card p-8 rounded-3xl border-0 animate-fade-in cursor-pointer relative overflow-hidden group" 
                     style="animation-delay: ${index * 100}ms"
                     onclick="handleProjectClick(${p.id})">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/5 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-700"></div>
                    <div class="flex justify-between items-start mb-6 relative">
                        <h3 class="font-bold text-indigo-950 text-2xl tracking-tight">${p.name}</h3>
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest ${statusClass} status-badge">${p.status.replace('_', ' ')}</span>
                    </div>
                    <div class="space-y-4 relative">
                        <div class="flex items-center gap-3 text-sm text-indigo-900/60 font-medium">
                            <div class="bg-indigo-50 p-1.5 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            ${p.user_name}
                        </div>
                        <div class="flex items-center gap-3 text-sm text-indigo-900/60 font-medium">
                            <div class="bg-indigo-50 p-1.5 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            Updated: ${new Date(p.updated_at).toLocaleDateString()}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    window.handleProjectClick = async (id) => {
        switchView('detail');
        ui.projectContent.innerHTML = `<div class="p-12 text-center text-slate-400">Loading details...</div>`;
        state.currentProject = await api.getProject(id);
        renderProjectDetail();
    };

    function renderProjectDetail() {
        const p = state.currentProject;
        const itemsBySection = p.items.reduce((acc, item) => {
            if (!acc[item.section]) acc[item.section] = [];
            acc[item.section].push(item);
            return acc;
        }, {});

        const allDone = p.items.every(item => item.status === 'pass');

        const sectionLabels = {
            core: 'Core Checklist',
            woocommerce: 'WooCommerce',
            forms: 'Forms',
            seo: 'SEO Analysis'
        };

        ui.projectContent.innerHTML = `
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 pb-12 animate-fade-in">
                <div class="lg:col-span-3 space-y-8">
                    ${Object.entries(itemsBySection).map(([section, items]) => `
                        <div class="glass-card rounded-3xl border-0 overflow-hidden shadow-2xl">
                            <div class="bg-indigo-900/5 px-8 py-5 border-b border-indigo-900/5">
                                <h3 class="font-bold text-indigo-950 text-xl tracking-tight">${sectionLabels[section]}</h3>
                            </div>
                            <div class="divide-y divide-indigo-900/5">
                                ${items.map(item => `
                                    <div class="p-6 flex items-start gap-6 hover:bg-white/40 transition-all duration-300">
                                        <div class="flex flex-col gap-3 pt-1">
                                            <button 
                                                onclick="toggleItemStatus(${item.id}, 'pass')" 
                                                class="w-8 h-8 rounded-xl flex items-center justify-center transition-all ${item.status === 'pass' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-200' : 'bg-white/50 text-slate-400 hover:text-emerald-500 hover:bg-white'}"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                            <button 
                                                onclick="toggleItemStatus(${item.id}, 'fail')" 
                                                class="w-8 h-8 rounded-xl flex items-center justify-center transition-all ${item.status === 'fail' ? 'bg-red-500 text-white shadow-lg shadow-red-200' : 'bg-white/50 text-slate-400 hover:text-red-500 hover:bg-white'}"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-indigo-950 font-semibold text-lg ${item.status === 'pass' ? 'line-through text-slate-400 opacity-60' : ''}">${item.label}</p>
                                            ${item.comment ? `
                                                <div class="mt-4 p-4 rounded-2xl border ${item.comment.startsWith('Warning:') ? 'bg-amber-50/50 border-amber-200 text-amber-900 shadow-sm' : 'bg-indigo-50/50 border-indigo-100 text-indigo-900/60'} text-sm font-medium animate-fade-in">
                                                    <div class="flex gap-3">
                                                        ${item.comment.startsWith('Warning:') ? `
                                                            <div class="bg-amber-500 text-white rounded-lg p-1 h-fit shadow-lg shadow-amber-200">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                                </svg>
                                                            </div>
                                                        ` : `
                                                            <div class="text-indigo-400 mt-1">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                                </svg>
                                                            </div>
                                                        `}
                                                        <div class="flex-1 italic">
                                                            ${item.comment}
                                                        </div>
                                                    </div>
                                                </div>
                                            ` : ''}
                                            ${item.status === 'fail' ? `
                                                <textarea 
                                                    class="mt-4 w-full text-sm border-indigo-100 bg-white/50 rounded-xl focus:ring-red-200 focus:border-red-400 focus:bg-white transition-all" 
                                                    placeholder="Add feedback for failure..."
                                                    onblur="updateItemComment(${item.id}, this.value)"
                                                >${item.comment || ''}</textarea>
                                            ` : ''}
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `).join('')}
                </div>

                <div class="space-y-8">
                    <div class="glass-card p-8 rounded-3xl border-0 sticky top-12 shadow-2xl">
                        <h4 class="font-bold text-indigo-950 text-xl mb-6 tracking-tight">Project Status</h4>
                        <div class="space-y-6">
                            <div>
                                <label class="text-[10px] font-black text-indigo-900/40 uppercase tracking-[0.2em] block mb-3">Status Pipeline</label>
                                <span class="px-4 py-2 rounded-full text-xs font-black uppercase tracking-widest ${ui.statusColors[p.status]} status-badge">${p.status.replace('_', ' ')}</span>
                            </div>
                            
                            <div class="pt-6 border-t border-indigo-900/5 space-y-3">
                                <button 
                                    id="run-audit-btn"
                                    onclick="handleRunAudit()"
                                    class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl shadow-xl shadow-indigo-600/20 transition-all font-bold flex items-center justify-center gap-2 active:scale-95 group"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:rotate-12 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    Run Auto Audit
                                </button>

                                <button 
                                    onclick="updateProjectStatus('${p.status === 'IN_QA' ? 'COMPLETED' : 'IN_QA'}')"
                                    class="w-full py-3.5 bg-white hover:bg-indigo-50 border border-indigo-100 text-indigo-700 rounded-2xl transition-all font-bold active:scale-95"
                                    ${!allDone && p.status !== 'IN_QA' ? 'disabled' : ''}
                                >
                                    ${p.status === 'IN_QA' ? 'Mark as Completed' : 'Send to QA'}
                                </button>
                                
                                ${p.status === 'IN_QA' ? `
                                    <button 
                                        onclick="updateProjectStatus('FAILED')"
                                        class="w-full py-3.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-2xl transition-all font-bold border border-red-100 active:scale-95"
                                    >
                                        Reject Result
                                    </button>
                                ` : ''}

                                <button 
                                    onclick="archiveProject()"
                                    class="w-full py-3.5 text-indigo-900/40 hover:text-indigo-900/60 text-xs font-bold transition-colors"
                                >
                                    Archive Pipeline
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    window.toggleItemStatus = async (itemId, status) => {
        try {
            const item = state.currentProject.items.find(i => i.id == itemId);
            if (!item) {
                console.error('Item not found in state:', itemId);
                return;
            }

            // If clicking same status, reset to pending
            const newStatus = item.status === status ? 'pending' : status;
            
            await api.updateItem(itemId, { status: newStatus });
            item.status = newStatus;
            renderProjectDetail();
        } catch (error) {
            console.error('Failed to toggle item status:', error);
        }
    };

    window.updateItemComment = async (itemId, comment) => {
        try {
            await api.updateItem(itemId, { comment });
            const item = state.currentProject.items.find(i => i.id == itemId);
            if (item) item.comment = comment;
        } catch (error) {
            console.error('Failed to update comment:', error);
        }
    };

    window.updateProjectStatus = async (status) => {
        try {
            await api.updateProject(state.currentProject.id, { status });
            state.currentProject.status = status;
            renderProjectDetail();
        } catch (error) {
            console.error('Failed to update project status:', error);
        }
    };

    window.archiveProject = async () => {
        if (!confirm('Are you sure you want to archive this project?')) return;
        try {
            await api.updateProject(state.currentProject.id, { is_archived: true });
            switchView('dashboard');
        } catch (error) {
            console.error('Failed to archive project:', error);
        }
    };

    window.handleRunAudit = async () => {
        const btn = document.getElementById('run-audit-btn');
        if (!btn || state.loading) return;

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Auditing...`;
        state.loading = true;

        try {
            const data = await api.runAudit(state.currentProject.id);
            if (data.success) {
                // Refresh project data to see updated checklist items
                state.currentProject = await api.getProject(state.currentProject.id);
                renderProjectDetail();
                alert('Audit completed and checklist updated!');
            } else {
                alert('Audit encountered an issue: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Audit failed:', error);
            alert('Failed to connect to the audit engine.');
        } finally {
            state.loading = false;
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    };

    init();
})();
