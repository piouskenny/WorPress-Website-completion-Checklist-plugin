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
        getUsers: () => api.fetch('/users')
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
                <div class="col-span-full py-20 flex flex-col items-center justify-center bg-white rounded-2xl border-2 border-dashed border-slate-200">
                    <p class="text-slate-500 mb-4">No projects found. Create your first one!</p>
                </div>
            `;
            return;
        }

        ui.projectsGrid.innerHTML = state.projects.map(p => {
            const statusClass = ui.statusColors[p.status] || 'bg-slate-100';
            return `
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow cursor-pointer" onclick="handleProjectClick(${p.id})">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="font-bold text-slate-800 text-lg">${p.name}</h3>
                        <span class="px-2 py-1 rounded text-[10px] font-bold uppercase ${statusClass}">${p.status.replace('_', ' ')}</span>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-sm text-slate-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            ${p.user_name}
                        </div>
                        <div class="flex items-center gap-2 text-sm text-slate-500">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
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
            seo: 'SEO'
        };

        ui.projectContent.innerHTML = `
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-6">
                    ${Object.entries(itemsBySection).map(([section, items]) => `
                        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                            <div class="bg-slate-50 px-6 py-3 border-b border-slate-100">
                                <h3 class="font-bold text-slate-700">${sectionLabels[section]}</h3>
                            </div>
                            <div class="divide-y divide-slate-100">
                                ${items.map(item => `
                                    <div class="p-4 flex items-start gap-4 hover:bg-slate-50/50 transition-colors">
                                        <div class="flex flex-col gap-2 pt-1">
                                            <button 
                                                onclick="toggleItemStatus(${item.id}, 'pass')" 
                                                class="w-6 h-6 rounded flex items-center justify-center transition-colors ${item.status === 'pass' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-300 hover:text-emerald-500 hover:bg-emerald-50'}"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                            <button 
                                                onclick="toggleItemStatus(${item.id}, 'fail')" 
                                                class="w-6 h-6 rounded flex items-center justify-center transition-colors ${item.status === 'fail' ? 'bg-red-500 text-white' : 'bg-slate-100 text-slate-300 hover:text-red-500 hover:bg-red-50'}"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-slate-700 font-medium ${item.status === 'pass' ? 'line-through text-slate-400' : ''}">${item.label}</p>
                                            ${item.status === 'fail' ? `
                                                <textarea 
                                                    class="mt-2 w-full text-sm border-slate-200 rounded-md focus:ring-red-200 focus:border-red-400" 
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

                <div class="space-y-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 sticky top-12">
                        <h4 class="font-bold text-slate-800 mb-4">Project Controls</h4>
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-2">Current Status</label>
                                <span class="px-3 py-1 rounded-full text-sm font-medium ${ui.statusColors[p.status]}">${p.status.replace('_', ' ')}</span>
                            </div>
                            
                            <div class="pt-4 border-t border-slate-100 space-y-2">
                                <button 
                                    onclick="updateProjectStatus('${p.status === 'IN_QA' ? 'COMPLETED' : 'IN_QA'}')"
                                    class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium"
                                    ${!allDone && p.status !== 'IN_QA' ? 'disabled' : ''}
                                >
                                    ${p.status === 'IN_QA' ? 'Mark as Completed' : 'Send to QA'}
                                </button>
                                
                                ${p.status === 'IN_QA' ? `
                                    <button 
                                        onclick="updateProjectStatus('FAILED')"
                                        class="w-full py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors font-medium border border-red-100"
                                    >
                                        Reject & Return to Dev
                                    </button>
                                ` : ''}

                                <button 
                                    onclick="archiveProject()"
                                    class="w-full py-2 text-slate-400 hover:text-slate-600 text-sm transition-colors"
                                >
                                    Archive Project
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

    init();
})();
