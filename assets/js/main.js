/**
 * STUDIFY – Main JavaScript
 * Toast notifications, dark mode, animations, Pomodoro, AJAX helpers
 */

// ─── Toast Notification System ───
const StudifyToast = {
    container: null,

    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },

    show(type, title, message, duration = 4000) {
        this.init();

        const icons = {
            success: 'fas fa-check',
            error: 'fas fa-times',
            warning: 'fas fa-exclamation',
            info: 'fas fa-info'
        };

        const toast = document.createElement('div');
        toast.className = `studify-toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon"><i class="${icons[type] || icons.info}"></i></div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.classList.add('toast-exit'); setTimeout(() => this.parentElement.remove(), 300);">
                <i class="fas fa-times"></i>
            </button>
        `;

        this.container.appendChild(toast);

        // Auto remove
        setTimeout(() => {
            if (toast.parentElement) {
                toast.classList.add('toast-exit');
                setTimeout(() => toast.remove(), 300);
            }
        }, duration);
    },

    success(title, message) { this.show('success', title, message); },
    error(title, message) { this.show('error', title, message); },
    warning(title, message) { this.show('warning', title, message); },
    info(title, message) { this.show('info', title, message); }
};

// ─── Dark Mode Toggle ───
const DarkMode = {
    init() {
        const saved = localStorage.getItem('studify-theme');
        if (saved === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            // Default to light mode
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('studify-theme', 'light');
        }
        // Add smooth transition class for future toggles
        requestAnimationFrame(() => {
            document.documentElement.classList.add('theme-transition-ready');
        });
        this.updateIcons();
    },

    toggle() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

        // Create a flash animation overlay for dramatic effect
        const flash = document.createElement('div');
        flash.style.cssText = `
            position: fixed; inset: 0; z-index: 99999;
            background: ${isDark ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.15)'};
            pointer-events: none;
            animation: themeFlash 0.5s ease-out forwards;
        `;
        document.body.appendChild(flash);
        setTimeout(() => flash.remove(), 600);

        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('studify-theme', 'light');
            if (typeof StudifyToast !== 'undefined') {
                StudifyToast.show('info', '☀️ Light Mode', 'Switched to light theme', 2000);
            }
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('studify-theme', 'dark');
            if (typeof StudifyToast !== 'undefined') {
                StudifyToast.show('info', '🌙 Dark Mode', 'Switched to dark theme', 2000);
            }
        }
        this.updateIcons();
    },

    updateIcons() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        document.querySelectorAll('.dark-mode-toggle i').forEach(icon => {
            icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
        });
        // Add spin animation to the icon
        document.querySelectorAll('.dark-mode-toggle i').forEach(icon => {
            icon.style.animation = 'none';
            requestAnimationFrame(() => {
                icon.style.animation = 'themeIconSpin 0.5s ease';
            });
        });
    }
};

// ─── Sidebar Toggle (Mobile + Desktop Collapse) ───
const Sidebar = {
    init() {
        const toggle = document.querySelector('.topbar-toggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        const collapseBtn = document.getElementById('sidebarCollapseBtn');

        // Mobile hamburger toggle
        if (toggle && sidebar) {
            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('show');
                if (overlay) overlay.classList.toggle('show');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }

        // Desktop collapse toggle
        if (collapseBtn && sidebar) {
            // Restore saved state
            const collapsed = localStorage.getItem('studify-sidebar-collapsed') === 'true';
            if (collapsed) {
                document.body.classList.add('sidebar-collapsed');
            }

            collapseBtn.addEventListener('click', () => {
                document.body.classList.toggle('sidebar-collapsed');
                const isCollapsed = document.body.classList.contains('sidebar-collapsed');
                localStorage.setItem('studify-sidebar-collapsed', isCollapsed);
            });
        }
    }
};

// ─── Scroll Animations ───
const ScrollAnimations = {
    init() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

        document.querySelectorAll('.fade-in-up').forEach(el => observer.observe(el));
    }
};

// ─── Landing Nav Scroll ───
const LandingNav = {
    init() {
        const nav = document.querySelector('.landing-nav');
        if (!nav) return;

        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        });
    }
};

// ─── CSRF Token Helper ───
function getCSRFToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

// ─── AJAX Task Toggle ───
function toggleTaskStatus(taskId, baseUrl, nextStatus = '') {
    const payload = new URLSearchParams({
        action: 'toggle_status',
        task_id: String(taskId),
        csrf_token: getCSRFToken()
    });
    if (nextStatus) {
        payload.append('next_status', nextStatus);
    }

    fetch(baseUrl + 'student/tasks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: payload.toString()
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                StudifyToast.success('Task Updated', data.message || 'Status changed successfully');
                setTimeout(() => location.reload(), 800);
            } else {
                StudifyToast.error('Error', data.message || 'Failed to update task');
            }
        })
        .catch(() => StudifyToast.error('Error', 'Network error. Please try again.'));
}

// ─── AJAX Task Delete ───
function deleteTask(taskId, baseUrl) {
    fetch(baseUrl + 'student/tasks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: `action=delete&task_id=${taskId}&csrf_token=${getCSRFToken()}`
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                StudifyToast.success('Task Deleted', 'Task removed successfully');
                const card = document.getElementById('task-' + taskId);
                if (card) {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(20px)';
                    setTimeout(() => {
                        card.remove();
                        const container = document.querySelector('.task-list');
                        if (container && container.children.length === 0) {
                            location.reload();
                        }
                    }, 300);
                }
            } else {
                StudifyToast.error('Error', data.message || 'Failed to delete task');
            }
        })
        .catch(() => StudifyToast.error('Error', 'Network error. Please try again.'));
}

// ─── Pomodoro Timer ───
const PomodoroTimer = {
    duration: 25 * 60,
    remaining: 25 * 60,
    isRunning: false,
    interval: null,
    isBreak: false,
    circumference: 814, // 2 * PI * 130 (radius of SVG circle)

    init() {
        this.updateDisplay();
        this.updateProgress(1);
    },

    start() {
        if (this.isRunning) return;
        this.isRunning = true;

        this.interval = setInterval(() => {
            this.remaining--;
            this.updateDisplay();
            this.updateProgress(this.remaining / this.duration);

            if (this.remaining <= 0) {
                this.complete();
            }
        }, 1000);

        document.getElementById('startBtn')?.setAttribute('disabled', 'true');
        document.getElementById('pauseBtn')?.removeAttribute('disabled');
    },

    pause() {
        this.isRunning = false;
        clearInterval(this.interval);
        document.getElementById('startBtn')?.removeAttribute('disabled');
        document.getElementById('pauseBtn')?.setAttribute('disabled', 'true');
    },

    reset() {
        this.pause();
        this.remaining = this.duration;
        this.updateDisplay();
        this.updateProgress(1);
    },

    setDuration(minutes) {
        this.duration = minutes * 60;
        this.remaining = this.duration;
        this.isBreak = (minutes === 5 || minutes === 15);
        this.updateDisplay();
        this.updateProgress(1);

        // Update ring color
        const ring = document.querySelector('.progress-ring');
        if (ring) {
            ring.classList.toggle('break-mode', this.isBreak);
        }

        // Update label
        const label = document.querySelector('.timer-label');
        if (label) {
            if (minutes === 25) label.textContent = 'Focus Time';
            else if (minutes === 5) label.textContent = 'Short Break';
            else if (minutes === 15) label.textContent = 'Long Break';
        }
    },

    updateDisplay() {
        const mins = Math.floor(this.remaining / 60);
        const secs = this.remaining % 60;
        const display = document.querySelector('.timer-display .time');
        if (display) {
            display.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
    },

    updateProgress(ratio) {
        const ring = document.querySelector('.progress-ring');
        if (ring) {
            const offset = this.circumference * (1 - ratio);
            ring.style.strokeDashoffset = offset;
        }
    },

    complete() {
        this.pause();

        if (!this.isBreak) {
            // Save study session via AJAX
            const baseUrl = document.querySelector('meta[name="base-url"]')?.content || '../';
            fetch(baseUrl + 'student/pomodoro.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=save_session&duration=${this.duration / 60}&type=Focus&csrf_token=${getCSRFToken()}`
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        StudifyToast.success('Session Complete! 🎉', `${this.duration / 60} minutes of focused study logged.`);
                    }
                })
                .catch(() => { });
        }

        // Play notification sound (if available)
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ==');
            audio.play().catch(() => { });
        } catch (e) { }

        StudifyToast.info('Timer Finished', this.isBreak ? 'Break is over! Time to focus.' : 'Great work! Take a break.');
    }
};

// ─── Initialize Everything ───
document.addEventListener('DOMContentLoaded', () => {
    DarkMode.init();
    Sidebar.init();
    ScrollAnimations.init();
    LandingNav.init();
    GlobalSearch.init();
    KeyboardShortcuts.init();
    QuickAdd.init();
    StudifyConfirm.init();

    // Initialize tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));

    // Initialize Pomodoro if on page
    if (document.querySelector('.timer-circle')) {
        PomodoroTimer.init();
    }

    // Initialize Focus Ambiance if on Pomodoro page
    if (document.querySelector('.ambiance-btn')) {
        FocusAmbiance.init();
    }

    // Auto-hide PHP alerts after 5 seconds
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // Show session messages as toasts
    const flashMessage = document.querySelector('[data-flash-message]');
    if (flashMessage) {
        const type = flashMessage.dataset.flashType || 'info';
        const msg = flashMessage.dataset.flashMessage;
        if (msg) {
            StudifyToast.show(type, type.charAt(0).toUpperCase() + type.slice(1), msg);
        }
    }
});

// ─── Global Search (Ctrl+K / Cmd+K) ───
const GlobalSearch = {
    modal: null,
    input: null,
    results: null,
    debounceTimer: null,
    selectedIndex: -1,

    init() {
        this.modal = document.getElementById('searchModal');
        this.input = document.getElementById('globalSearchInput');
        this.results = document.getElementById('searchResults');

        if (!this.modal || !this.input) return;

        // Input handler with debounce
        this.input.addEventListener('input', () => {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.search(), 300);
        });

        // Keyboard navigation inside search
        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.close();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.navigate(1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.navigate(-1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                this.selectCurrent();
            }
        });

        // Click outside to close
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) this.close();
        });

        // ESC on the overlay itself
        this.modal.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.stopPropagation();
                this.close();
            }
        });
    },

    open() {
        if (!this.modal) return;
        this.modal.style.display = 'flex';
        this.input.value = '';
        this.input.focus();
        this.selectedIndex = -1;
        this.results.innerHTML = `
            <div class="search-empty">
                <i class="fas fa-search" style="font-size: 24px; opacity: 0.3;"></i>
                <p style="margin-top: 8px; font-size: 13px; color: var(--text-muted);">Type to search across your tasks, notes, and subjects</p>
            </div>
        `;
        document.body.style.overflow = 'hidden';
    },

    close() {
        if (!this.modal) return;
        this.modal.style.display = 'none';
        document.body.style.overflow = '';
    },

    async search() {
        const query = this.input.value.trim();
        if (query.length < 2) {
            this.results.innerHTML = `
                <div class="search-empty">
                    <i class="fas fa-search" style="font-size: 24px; opacity: 0.3;"></i>
                    <p style="margin-top: 8px; font-size: 13px; color: var(--text-muted);">Type at least 2 characters to search</p>
                </div>
            `;
            return;
        }

        const baseUrl = document.querySelector('meta[name="base-url"]')?.content || '../';

        try {
            const response = await fetch(`${baseUrl}student/global_search.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success && data.results.length > 0) {
                this.renderResults(data.results, baseUrl);
            } else {
                this.results.innerHTML = `
                    <div class="search-empty">
                        <i class="fas fa-search" style="font-size: 24px; opacity: 0.3;"></i>
                        <p style="margin-top: 8px; font-size: 13px; color: var(--text-muted);">No results found for "${query}"</p>
                    </div>
                `;
            }
        } catch (e) {
            this.results.innerHTML = '<div class="search-empty"><p class="text-danger">Search error</p></div>';
        }
    },

    renderResults(results, baseUrl) {
        let html = '';
        let lastType = '';

        results.forEach((r, i) => {
            if (r.type !== lastType) {
                const labels = { task: 'Tasks', note: 'Notes', subject: 'Subjects' };
                html += `<div class="search-result-group">${labels[r.type] || r.type}</div>`;
                lastType = r.type;
            }

            const icons = { task: 'fa-check-circle', note: 'fa-sticky-note', subject: 'fa-book' };
            const links = {
                task: `${baseUrl}student/tasks.php`,
                note: `${baseUrl}student/notes.php`,
                subject: `${baseUrl}student/subjects.php`
            };

            const title = r.data.title || r.data.name || '';
            const meta = r.data.subject_name || r.data.semester_name || r.data.code || '';
            const statusBadge = r.data.status ? ` · ${r.data.status}` : '';

            html += `
                <a href="${links[r.type]}" class="search-result-item" data-index="${i}">
                    <div class="search-result-icon ${r.type}">
                        <i class="fas ${icons[r.type]}"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div class="search-result-title">${this.escapeHtml(title)}</div>
                        <div class="search-result-meta">${this.escapeHtml(meta)}${statusBadge}</div>
                    </div>
                </a>
            `;
        });

        this.results.innerHTML = html;
        this.selectedIndex = -1;
    },

    navigate(direction) {
        const items = this.results.querySelectorAll('.search-result-item');
        if (items.length === 0) return;

        items.forEach(i => i.classList.remove('active'));
        this.selectedIndex += direction;

        if (this.selectedIndex < 0) this.selectedIndex = items.length - 1;
        if (this.selectedIndex >= items.length) this.selectedIndex = 0;

        items[this.selectedIndex].classList.add('active');
        items[this.selectedIndex].scrollIntoView({ block: 'nearest' });
    },

    selectCurrent() {
        const items = this.results.querySelectorAll('.search-result-item');
        if (this.selectedIndex >= 0 && this.selectedIndex < items.length) {
            window.location.href = items[this.selectedIndex].href;
        }
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// ─── Quick Add Task (Ctrl+Q) ───
const QuickAdd = {
    modal: null,
    form: null,
    
    init() {
        // Create quick add modal if it doesn't exist
        if (!document.getElementById('quickAddModal')) {
            const modal = document.createElement('div');
            modal.id = 'quickAddModal';
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-bolt"></i> Quick Add Task</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="quickAddForm">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="type" value="Assignment">
                                <input type="hidden" name="description" value="">
                                <input type="hidden" name="is_recurring" value="0">
                                <input type="hidden" name="csrf_token" id="quickAddCSRF" value="">
                                
                                <div class="mb-3">
                                    <label class="form-label">Task Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" placeholder="e.g., Complete assignment" required autofocus>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Priority</label>
                                        <select class="form-select" name="priority">
                                            <option value="Medium" selected>Medium</option>
                                            <option value="High">High</option>
                                            <option value="Low">Low</option>
                                        </select>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Deadline <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control" name="deadline" required>
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <label class="form-label">Subject <small class="text-muted">(optional)</small></label>
                                    <select class="form-select" name="subject_id" id="quickAddSubject">
                                        <option value="">— None —</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Task</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            this.modal = new bootstrap.Modal(modal);
            this.form = document.getElementById('quickAddForm');
            
            // Set CSRF token
            const csrfInput = this.form.querySelector('#quickAddCSRF');
            if (csrfInput) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                csrfInput.value = csrfToken;
            }
            
            // Set default deadline to tomorrow at 9 AM
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(9, 0, 0, 0);
            const deadlineInput = this.form.querySelector('input[name="deadline"]');
            if (deadlineInput) {
                deadlineInput.value = tomorrow.toISOString().slice(0, 16);
            }
            
            // Load subjects
            this.loadSubjects();
            
            // Handle form submission
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        } else {
            this.modal = bootstrap.Modal.getInstance(document.getElementById('quickAddModal'));
            this.form = document.getElementById('quickAddForm');
        }
    },
    
    loadSubjects() {
        const baseUrl = document.querySelector('meta[name="base-url"]')?.content || '../';
        fetch(baseUrl + 'student/subjects.php?action=list')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.subjects) {
                    const select = document.getElementById('quickAddSubject');
                    select.innerHTML = '<option value="">— None —</option>';
                    data.subjects.forEach(sub => {
                        const option = document.createElement('option');
                        option.value = sub.id;
                        option.textContent = sub.name;
                        select.appendChild(option);
                    });
                }
            })
            .catch(() => {});
    },
    
    handleSubmit(e) {
        e.preventDefault();
        const formData = new FormData(this.form);
        const baseUrl = document.querySelector('meta[name="base-url"]')?.content || '../';
        
        // Add CSRF token if not already in form
        if (!formData.has('csrf_token')) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }
        }
        
        // Debug: Log form data
        console.log('Quick Add - Submitting:', {
            title: formData.get('title'),
            deadline: formData.get('deadline'),
            priority: formData.get('priority'),
            has_csrf: formData.has('csrf_token')
        });
        
        StudifyToast.info('Adding Task...', 'Please wait');
        
        fetch(baseUrl + 'student/tasks.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(r => {
            // Treat non-2xx as hard failure
            if (!r.ok) {
                throw new Error('Request failed with status ' + r.status);
            }

            const contentType = r.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                return r.json();
            }

            // Fallback for HTML responses (e.g., validation errors rendered as full page)
            return r.text().then(text => {
                const success = text.includes('Task added successfully');
                return {
                    success,
                    message: success ? 'Task added successfully' : (text.trim() || 'Failed to add task')
                };
            });
        })
        .then(data => {
            console.log('Quick Add - Response:', data);
            if (data.success) {
                StudifyToast.success('Task Added', data.message || 'Task created successfully');
                this.modal.hide();
                this.form.reset();

                // Stay on the current page; only refresh tasks list if we are already there
                if (window.location.pathname.includes('tasks.php')) {
                    setTimeout(() => window.location.reload(), 400);
                }
            } else {
                StudifyToast.error('Error', data.message || 'Failed to add task');
                console.error('Quick Add - Error:', data.message);
            }
        })
        .catch((error) => {
            console.error('Quick add error:', error);
            StudifyToast.error('Error', 'Network error. Please try again.');
        });
    },
    
    open() {
        this.init();
        // Update CSRF token each time modal opens
        const csrfInput = this.form.querySelector('#quickAddCSRF');
        if (csrfInput) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            csrfInput.value = csrfToken;
        }
        this.modal.show();
        // Focus on title input
        setTimeout(() => {
            const titleInput = this.form.querySelector('input[name="title"]');
            if (titleInput) titleInput.focus();
        }, 300);
    }
};

// ─── Keyboard Shortcuts ───
const KeyboardShortcuts = {
    init() {
        document.addEventListener('keydown', (e) => {
            // Don't trigger in inputs/textareas/contenteditable (e.g. Quill editor)
            const el = document.activeElement;
            const tag = el?.tagName;
            const isInput = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT'
                || el?.isContentEditable || el?.closest?.('.ql-editor');

            // Ctrl+K / Cmd+K → Open search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                GlobalSearch.open();
                return;
            }

            // ESC → Close search (handled by search input too)
            if (e.key === 'Escape') {
                GlobalSearch.close();
                return;
            }

            // Skip other shortcuts if user is typing
            if (isInput) return;

            const baseUrl = document.querySelector('meta[name="base-url"]')?.content || '../';

            // Ctrl+Q / Cmd+Q → Quick Add Task
            if ((e.ctrlKey || e.metaKey) && e.key === 'q') {
                e.preventDefault();
                QuickAdd.open();
                return;
            }

            // N → New task (go to tasks page)
            if (e.key === 'n' || e.key === 'N') {
                window.location.href = baseUrl + 'student/tasks.php';
                return;
            }

            // P → Pomodoro
            if (e.key === 'p' || e.key === 'P') {
                window.location.href = baseUrl + 'student/pomodoro.php';
                return;
            }

            // D → Dashboard
            if (e.key === 'd' || e.key === 'D') {
                window.location.href = baseUrl + 'student/dashboard.php';
                return;
            }

            // / → Focus search
            if (e.key === '/') {
                e.preventDefault();
                GlobalSearch.open();
                return;
            }
        });
    }
};

// ─── Focus Ambiance System ───
const FocusAmbiance = {
    ctx: null,
    sounds: {},
    active: new Set(),
    _paused: new Set(),

    // Generate ambient noise using Web Audio API (no external files needed)
    _createNoise(type) {
        if (!this.ctx) this.ctx = new (window.AudioContext || window.webkitAudioContext)();
        const ctx = this.ctx;
        const gain = ctx.createGain();
        gain.gain.value = 0;
        gain.connect(ctx.destination);

        let source;
        if (type === 'rain') {
            // Brown noise filtered for rain-like sound
            const bufferSize = 2 * ctx.sampleRate;
            const buffer = ctx.createBuffer(2, bufferSize, ctx.sampleRate);
            for (let ch = 0; ch < 2; ch++) {
                const data = buffer.getChannelData(ch);
                let last = 0;
                for (let i = 0; i < bufferSize; i++) {
                    const white = Math.random() * 2 - 1;
                    data[i] = (last + (0.02 * white)) / 1.02;
                    last = data[i];
                    data[i] *= 3.5;
                }
            }
            source = ctx.createBufferSource();
            source.buffer = buffer;
            source.loop = true;
            const hp = ctx.createBiquadFilter();
            hp.type = 'highpass';
            hp.frequency.value = 400;
            const lp = ctx.createBiquadFilter();
            lp.type = 'lowpass';
            lp.frequency.value = 8000;
            source.connect(hp);
            hp.connect(lp);
            lp.connect(gain);
        } else if (type === 'fire') {
            // Crackling fire: filtered noise with low-frequency emphasis
            const bufferSize = 2 * ctx.sampleRate;
            const buffer = ctx.createBuffer(2, bufferSize, ctx.sampleRate);
            for (let ch = 0; ch < 2; ch++) {
                const data = buffer.getChannelData(ch);
                let last = 0;
                for (let i = 0; i < bufferSize; i++) {
                    const white = Math.random() * 2 - 1;
                    data[i] = (last + (0.01 * white)) / 1.01;
                    last = data[i];
                    data[i] *= 5;
                    // Random crackle pops
                    if (Math.random() < 0.0003) data[i] += (Math.random() - 0.5) * 0.3;
                }
            }
            source = ctx.createBufferSource();
            source.buffer = buffer;
            source.loop = true;
            const lp = ctx.createBiquadFilter();
            lp.type = 'lowpass';
            lp.frequency.value = 2000;
            source.connect(lp);
            lp.connect(gain);
        } else if (type === 'waves') {
            // Ocean: slow modulated noise
            const bufferSize = 4 * ctx.sampleRate;
            const buffer = ctx.createBuffer(2, bufferSize, ctx.sampleRate);
            for (let ch = 0; ch < 2; ch++) {
                const data = buffer.getChannelData(ch);
                let last = 0;
                for (let i = 0; i < bufferSize; i++) {
                    const t = i / ctx.sampleRate;
                    const wave = Math.sin(t * 0.15 * Math.PI * 2) * 0.5 + 0.5;
                    const white = Math.random() * 2 - 1;
                    data[i] = (last + (0.02 * white)) / 1.02;
                    last = data[i];
                    data[i] *= wave * 4;
                }
            }
            source = ctx.createBufferSource();
            source.buffer = buffer;
            source.loop = true;
            const lp = ctx.createBiquadFilter();
            lp.type = 'lowpass';
            lp.frequency.value = 3000;
            source.connect(lp);
            lp.connect(gain);
        } else if (type === 'birds') {
            // Birdsong: high frequency chirps over gentle noise bed
            const bufferSize = 6 * ctx.sampleRate;
            const buffer = ctx.createBuffer(2, bufferSize, ctx.sampleRate);
            for (let ch = 0; ch < 2; ch++) {
                const data = buffer.getChannelData(ch);
                for (let i = 0; i < bufferSize; i++) {
                    const t = i / ctx.sampleRate;
                    data[i] = (Math.random() - 0.5) * 0.05; // quiet background
                    // Random chirp bursts
                    if (Math.random() < 0.00008) {
                        const chirpLen = Math.floor(ctx.sampleRate * (0.05 + Math.random() * 0.15));
                        const freq = 2000 + Math.random() * 4000;
                        for (let j = 0; j < chirpLen && (i + j) < bufferSize; j++) {
                            const env = Math.sin((j / chirpLen) * Math.PI);
                            data[i + j] += Math.sin(2 * Math.PI * freq * j / ctx.sampleRate) * env * 0.12;
                        }
                    }
                }
            }
            source = ctx.createBufferSource();
            source.buffer = buffer;
            source.loop = true;
            source.connect(gain);
        } else if (type === 'coffee') {
            // Café: warm noise with muffled quality
            const bufferSize = 3 * ctx.sampleRate;
            const buffer = ctx.createBuffer(2, bufferSize, ctx.sampleRate);
            for (let ch = 0; ch < 2; ch++) {
                const data = buffer.getChannelData(ch);
                let last = 0;
                for (let i = 0; i < bufferSize; i++) {
                    const white = Math.random() * 2 - 1;
                    data[i] = (last + (0.03 * white)) / 1.03;
                    last = data[i];
                    data[i] *= 3;
                    // Occasional clink
                    if (Math.random() < 0.00004) {
                        const cLen = Math.floor(ctx.sampleRate * 0.02);
                        for (let j = 0; j < cLen && (i + j) < bufferSize; j++) {
                            data[i + j] += Math.sin(j / cLen * Math.PI * 2 * 4000) * Math.exp(-j / cLen * 5) * 0.05;
                        }
                    }
                }
            }
            source = ctx.createBufferSource();
            source.buffer = buffer;
            source.loop = true;
            const bp = ctx.createBiquadFilter();
            bp.type = 'bandpass';
            bp.frequency.value = 1200;
            bp.Q.value = 0.5;
            source.connect(bp);
            bp.connect(gain);
        } else if (type === 'wind') {
            // Wind: slowly modulated filtered noise
            const bufferSize = 5 * ctx.sampleRate;
            const buffer = ctx.createBuffer(2, bufferSize, ctx.sampleRate);
            for (let ch = 0; ch < 2; ch++) {
                const data = buffer.getChannelData(ch);
                let last = 0;
                for (let i = 0; i < bufferSize; i++) {
                    const t = i / ctx.sampleRate;
                    const mod = Math.sin(t * 0.3) * 0.3 + Math.sin(t * 0.07) * 0.4 + 0.5;
                    const white = Math.random() * 2 - 1;
                    data[i] = (last + (0.015 * white)) / 1.015;
                    last = data[i];
                    data[i] *= mod * 4;
                }
            }
            source = ctx.createBufferSource();
            source.buffer = buffer;
            source.loop = true;
            const bp = ctx.createBiquadFilter();
            bp.type = 'bandpass';
            bp.frequency.value = 800;
            bp.Q.value = 0.3;
            source.connect(bp);
            bp.connect(gain);
        }

        return { source, gain };
    },

    toggle(type) {
        if (this.active.has(type)) {
            this.stop(type);
        } else {
            this.start(type);
        }
    },

    start(type) {
        if (this.active.has(type)) return;
        const sound = this._createNoise(type);
        // Resume AudioContext after creation — browsers require user gesture
        if (this.ctx && this.ctx.state === 'suspended') this.ctx.resume();
        sound.source.start();
        const btn = document.querySelector(`.ambiance-btn[data-sound="${type}"]`);
        const vol = btn ? parseInt(btn.querySelector('.ambiance-volume').value) / 100 : 0.5;
        sound.gain.gain.setTargetAtTime(vol * 0.4, this.ctx.currentTime, 0.1);
        this.sounds[type] = sound;
        this.active.add(type);
        if (btn) btn.classList.add('active');
        this._updateStatus();
    },

    stop(type) {
        const sound = this.sounds[type];
        if (sound) {
            sound.gain.gain.setTargetAtTime(0, this.ctx.currentTime, 0.1);
            setTimeout(() => { try { sound.source.stop(); } catch (e) { } }, 200);
            delete this.sounds[type];
        }
        this.active.delete(type);
        this._paused.delete(type);
        const btn = document.querySelector(`.ambiance-btn[data-sound="${type}"]`);
        if (btn) btn.classList.remove('active');
        this._updateStatus();
    },

    stopAll() {
        [...this.active].forEach(t => this.stop(t));
        this._paused.clear();
    },

    setVolume(type, vol) {
        const sound = this.sounds[type];
        if (sound && this.ctx) {
            sound.gain.gain.setTargetAtTime(vol * 0.4, this.ctx.currentTime, 0.05);
        }
    },

    // Timer integration hooks
    onFocusStart() {
        const autoPlay = document.getElementById('ambianceAutoPlay');
        if (autoPlay && autoPlay.checked && this._paused.size > 0) {
            // Resume previously active sounds
            [...this._paused].forEach(t => this.start(t));
            this._paused.clear();
        }
    },

    onBreakStart() {
        // Pause active sounds during break
        this._paused = new Set(this.active);
        [...this.active].forEach(t => this.stop(t));
    },

    onPause() {
        // Keep sounds running during pause — user can stop manually
    },

    _updateStatus() {
        const status = document.getElementById('ambianceStatus');
        const stopBtn = document.getElementById('ambianceStopAll');
        if (!status) return;
        const count = this.active.size;
        if (count === 0) {
            status.innerHTML = '<i class="fas fa-volume-mute"></i> No sounds active';
            if (stopBtn) stopBtn.style.display = 'none';
        } else {
            const names = [...this.active].map(t => {
                const btn = document.querySelector(`.ambiance-btn[data-sound="${t}"]`);
                return btn ? btn.querySelector('.ambiance-label').textContent : t;
            });
            status.innerHTML = `<i class="fas fa-volume-up text-primary"></i> Playing: ${names.join(', ')}`;
            if (stopBtn) stopBtn.style.display = 'inline-block';
        }
    },

    init() {
        document.querySelectorAll('.ambiance-btn').forEach(btn => {
            const type = btn.dataset.sound;
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (e.target.closest('.ambiance-volume-wrap')) return;
                this.toggle(type);
            });
            const slider = btn.querySelector('.ambiance-volume');
            if (slider) {
                slider.addEventListener('input', (e) => {
                    e.stopPropagation();
                    this.setVolume(type, parseInt(e.target.value) / 100);
                });
                slider.addEventListener('click', (e) => e.stopPropagation());
            }
        });
        const stopBtn = document.getElementById('ambianceStopAll');
        if (stopBtn) stopBtn.addEventListener('click', () => this.stopAll());
    }
};

// ─── Global Toast Helper (shorthand) ───
function showToast(message, type = 'info') {
    const titles = { success: 'Success', error: 'Error', warning: 'Warning', info: 'Info' };
    StudifyToast.show(type, titles[type] || 'Notice', message);
}

// ─── Password Visibility Toggle ───
function togglePasswordVisibility(btn) {
    const input = btn.closest('.input-group').querySelector('input');
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
        btn.title = 'Hide password';
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
        btn.title = 'Show password';
    }
}

// ─── Confirmation Dialog System ───
const StudifyConfirm = {
    _resolve: null,
    _pendingForm: null,

    init() {
        const overlay = document.getElementById('confirmOverlay');
        const cancelBtn = document.getElementById('confirmCancel');
        const okBtn = document.getElementById('confirmOk');

        if (!overlay) return;

        cancelBtn?.addEventListener('click', () => this._close(false));
        okBtn?.addEventListener('click', () => this._close(true));
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) this._close(false);
        });

        document.addEventListener('keydown', (e) => {
            if (overlay.style.display !== 'none' && e.key === 'Escape') {
                this._close(false);
            }
        });
    },

    /**
     * Show the confirm dialog and return a Promise
     * @param {string} title - Dialog title
     * @param {string} message - Dialog message
     * @param {string} type - 'danger' | 'warning' | 'info' | 'success'
     * @param {string} confirmText - Text for the confirm button
     */
    show(title, message, type = 'danger', confirmText = 'Confirm') {
        const overlay = document.getElementById('confirmOverlay');
        const iconEl = document.getElementById('confirmIcon');
        const titleEl = document.getElementById('confirmTitle');
        const msgEl = document.getElementById('confirmMessage');
        const okBtn = document.getElementById('confirmOk');

        if (!overlay) return Promise.resolve(false);

        // Set content
        titleEl.textContent = title;
        msgEl.textContent = message;
        okBtn.textContent = confirmText;

        // Set icon and colors based on type
        const configs = {
            danger: { icon: 'fas fa-exclamation-triangle', color: 'var(--danger)', btnClass: 'btn-danger' },
            warning: { icon: 'fas fa-exclamation-circle', color: 'var(--warning)', btnClass: 'btn-warning' },
            info: { icon: 'fas fa-info-circle', color: 'var(--info)', btnClass: 'btn-info' },
            success: { icon: 'fas fa-check-circle', color: 'var(--success)', btnClass: 'btn-success' }
        };
        const cfg = configs[type] || configs.danger;

        iconEl.innerHTML = `<i class="${cfg.icon}"></i>`;
        iconEl.style.color = cfg.color;
        iconEl.style.background = cfg.color.replace(')', ', 0.1)').replace('var(', 'rgba(');
        // Use a simpler approach for background
        iconEl.className = `confirm-icon confirm-icon-${type}`;
        okBtn.className = `btn btn-sm ${cfg.btnClass}`;

        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        okBtn.focus();

        return new Promise((resolve) => {
            this._resolve = resolve;
        });
    },

    _close(confirmed) {
        const overlay = document.getElementById('confirmOverlay');
        if (overlay) {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        }

        if (this._resolve) {
            this._resolve(confirmed);
            this._resolve = null;
        }

        // Handle pending form submission
        if (confirmed && this._pendingForm) {
            const form = this._pendingForm;
            this._pendingForm = null;
            // Create and dispatch a new submit event that bypasses our handler
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = '_confirmed';
            hiddenInput.value = '1';
            form.appendChild(hiddenInput);
            form.submit();
        } else {
            this._pendingForm = null;
        }
    },

    /**
     * For form onsubmit: return StudifyConfirm.form(event, title, message, type)
     * Prevents form submission, shows dialog, submits form if confirmed.
     */
    form(event, title, message, type = 'danger') {
        event.preventDefault();
        const form = event.target;

        // Skip if already confirmed
        if (form.querySelector('input[name="_confirmed"]')) {
            return true;
        }

        this._pendingForm = form;
        this.show(title, message, type, type === 'danger' ? 'Delete' : 'Confirm');
        return false;
    },

    /**
     * For button onclick: return StudifyConfirm.buttonConfirm(event, title, message, type)
     * Prevents button click, shows dialog, resubmits form if confirmed.
     */
    buttonConfirm(event, title, message, type = 'warning') {
        event.preventDefault();
        const btn = event.target.closest('button');
        const form = btn?.closest('form');

        if (form) {
            this._pendingForm = form;
            this.show(title, message, type, 'Confirm');
        }
        return false;
    },

    /**
     * For callback-based actions (AJAX deletes, toggles, etc.)
     * StudifyConfirm.action(title, message, type, callback)
     */
    action(title, message, type, callback) {
        this.show(title, message, type, type === 'danger' ? 'Delete' : 'Confirm').then((confirmed) => {
            if (confirmed && typeof callback === 'function') {
                callback();
            }
        });
    },

    /**
     * Logout confirmation
     */
    logout(event, href) {
        event.preventDefault();
        this.show(
            'Log Out',
            'Are you sure you want to log out of your account?',
            'warning',
            'Log Out'
        ).then((confirmed) => {
            if (confirmed) {
                window.location.href = href;
            }
        });
        return false;
    }
};
