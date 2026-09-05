(function() {
            'use strict';

            // Shared backend display-date contract for inline widgets.
            const qlns = window.qlns = window.qlns || {};
            qlns.formatDisplayDate = function (value) {
                const match = typeof value === 'string'
                    ? /^(\d{4})-(\d{2})-(\d{2})(?:$|T|\s)/.exec(value.trim())
                    : null;
                if (!match) return '';

                const year = Number(match[1]);
                const month = Number(match[2]);
                const day = Number(match[3]);
                const date = new Date(Date.UTC(year, month - 1, day));
                if (date.getUTCFullYear() !== year
                    || date.getUTCMonth() !== month - 1
                    || date.getUTCDate() !== day) {
                    return '';
                }

                return `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}/${String(year).padStart(4, '0')}`;
            };

            // ===== DOM ELEMENTS =====
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggleSidebar');
            const toggleIcon = document.getElementById('toggleIcon');
            const backdrop = document.getElementById('sidebarBackdrop');
            const hamburgerBtn = document.getElementById('hamburgerBtn');

            // ===== CHECK MOBILE =====
            function isMobile() {
                return window.innerWidth <= 768;
            }

            // ===== TOGGLE SUB MENU =====
            function toggleSubMenu(link) {
                const parentItem = link.closest('.nav-item');
                if (!parentItem) return;

                const subMenu = parentItem.querySelector('.sub-menu');
                if (!subMenu) return;

                subMenu.classList.toggle('open');
                const arrow = link.querySelector('.menu-arrow');
                if (arrow) {
                    arrow.classList.toggle('rotated');
                }

                const parentMenu = parentItem.closest('.sub-menu') || parentItem.closest('.sidebar-menu');
                if (parentMenu) {
                    const siblings = parentMenu.querySelectorAll(':scope > .nav-item > .sub-menu.open');
                    siblings.forEach(function(sibling) {
                        if (sibling !== subMenu) {
                            sibling.classList.remove('open');
                            const siblingArrow = sibling.closest('.nav-item').querySelector('.menu-arrow');
                            if (siblingArrow) {
                                siblingArrow.classList.remove('rotated');
                            }
                        }
                    });
                }
            }

            document.querySelectorAll('[data-toggle="submenu"]').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    if (!isMobile() && sidebar.classList.contains('collapsed')) {
                        return;
                    }
                    e.preventDefault();
                    toggleSubMenu(this);
                });
            });

            // ===== OPEN MOBILE SIDEBAR =====
            function openMobileSidebar() {
                sidebar.classList.add('mobile-open');
                backdrop.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            // ===== CLOSE MOBILE SIDEBAR =====
            function closeMobileSidebar() {
                sidebar.classList.remove('mobile-open');
                backdrop.classList.remove('show');
                document.body.style.overflow = '';
            }

            // ===== TOGGLE MOBILE SIDEBAR =====
            function toggleMobileSidebar() {
                if (sidebar.classList.contains('mobile-open')) {
                    closeMobileSidebar();
                } else {
                    openMobileSidebar();
                }
            }

            // ===== TOGGLE SIDEBAR (Desktop) =====
            function toggleSidebar() {
                if (isMobile()) {
                    toggleMobileSidebar();
                    return;
                }

                sidebar.classList.toggle('collapsed');
                updateToggleIcon();

                if (sidebar.classList.contains('collapsed')) {
                    document.querySelectorAll('.sub-menu.open').forEach(function(sub) {
                        sub.classList.remove('open');
                        const arrow = sub.closest('.nav-item').querySelector('.menu-arrow');
                        if (arrow) {
                            arrow.classList.remove('rotated');
                        }
                    });
                }
            }

            // ===== UPDATE TOGGLE ICON =====
            function updateToggleIcon() {
                if (isMobile()) {
                    return;
                }
                if (sidebar.classList.contains('collapsed')) {
                    toggleIcon.className = 'bi bi-chevron-right';
                } else {
                    toggleIcon.className = 'bi bi-chevron-left';
                }
            }

            // ===== EVENT LISTENERS =====
            toggleBtn.addEventListener('click', toggleSidebar);
            hamburgerBtn.addEventListener('click', toggleMobileSidebar);

            backdrop.addEventListener('click', function() {
                if (isMobile() && sidebar.classList.contains('mobile-open')) {
                    closeMobileSidebar();
                }
            });

            // ===== RESIZE HANDLER =====
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    if (!isMobile()) {
                        if (sidebar.classList.contains('mobile-open')) {
                            closeMobileSidebar();
                        }
                        updateToggleIcon();
                    } else {
                        if (!sidebar.classList.contains('mobile-open')) {
                            backdrop.classList.remove('show');
                        }
                    }
                }, 150);
            });

            // ===== KEYBOARD SHORTCUT =====
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (isMobile() && sidebar.classList.contains('mobile-open')) {
                        closeMobileSidebar();
                    }
                    // Close dropdowns on ESC
                    document.querySelectorAll('.dropdown-menu-custom').forEach(function(d) {
                        d.style.display = 'none';
                    });
                }
            });

            // ===== INIT =====
            function initSidebar() {
                if (isMobile()) {
                    sidebar.classList.remove('collapsed');
                    sidebar.classList.remove('mobile-open');
                    backdrop.classList.remove('show');
                    document.body.style.overflow = '';
                } else {
                    sidebar.classList.remove('mobile-open');
                    sidebar.classList.remove('collapsed');
                    updateToggleIcon();
                    backdrop.classList.remove('show');
                    document.body.style.overflow = '';
                }
            }

            initSidebar();

            // =============================================
            // ===== DROPDOWN DYNAMIC POSITIONING =====
            // =============================================
            
            /**
             * Hàm định vị dropdown tự động
             * @param {HTMLElement} dropdown - Phần tử dropdown cần định vị
             * @param {HTMLElement} trigger - Phần tử kích hoạt dropdown
             */
            function positionDropdown(dropdown, trigger) {
                // Lấy vị trí của trigger
                const rect = trigger.getBoundingClientRect();
                
                // Tạm thời hiển thị dropdown để đo kích thước
                const wasHidden = dropdown.style.display === 'none' || dropdown.style.display === '';
                if (wasHidden) {
                    dropdown.style.display = 'block';
                    dropdown.style.visibility = 'hidden';
                    dropdown.style.opacity = '0';
                }
                
                // Lấy kích thước dropdown
                const dropdownRect = dropdown.getBoundingClientRect();
                const dropdownWidth = dropdownRect.width || 280;
                const dropdownHeight = dropdownRect.height || 300;
                
                // Vị trí mặc định: hiển thị bên dưới, căn trái với trigger
                let left = rect.left;
                let top = rect.bottom + 8;
                
                // Kiểm tra và điều chỉnh nếu tràn phải
                if (left + dropdownWidth > window.innerWidth - 10) {
                    left = window.innerWidth - dropdownWidth - 10;
                }
                
                // Kiểm tra và điều chỉnh nếu tràn trái
                if (left < 10) {
                    left = 10;
                }
                
                // Kiểm tra và điều chỉnh nếu tràn dưới
                if (top + dropdownHeight > window.innerHeight - 10) {
                    // Nếu tràn dưới, hiển thị lên trên
                    top = rect.top - dropdownHeight - 8;
                    // Nếu vẫn tràn, hiển thị ở dưới với scroll
                    if (top < 10) {
                        top = rect.bottom + 8;
                    }
                }
                
                // Áp dụng vị trí
                dropdown.style.position = 'fixed';
                dropdown.style.left = left + 'px';
                dropdown.style.top = top + 'px';
                dropdown.style.transform = 'none';
                dropdown.style.right = 'auto';
                dropdown.style.maxHeight = (window.innerHeight - top - 20) + 'px';
                dropdown.style.overflowY = 'auto';
                
                // Hiển thị lại dropdown
                if (wasHidden) {
                    dropdown.style.visibility = 'visible';
                    dropdown.style.opacity = '1';
                }
            }

            // ===== DROPDOWN TOGGLE =====
            document.querySelectorAll('[data-dropdown]').forEach(function(trigger) {
                trigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const dropdownId = this.getAttribute('data-dropdown');
                    const dropdown = document.getElementById(dropdownId);
                    if (!dropdown) return;

                    // Close all other dropdowns
                    document.querySelectorAll('.dropdown-menu-custom').forEach(function(d) {
                        if (d.id !== dropdownId && d.style.display !== 'none') {
                            d.style.display = 'none';
                        }
                    });

                    // Toggle current dropdown
                    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
                        dropdown.style.display = 'block';
                        // Reset styles trước khi định vị
                        dropdown.style.position = 'fixed';
                        dropdown.style.maxHeight = 'none';
                        dropdown.style.overflowY = 'visible';
                        
                        // Gọi hàm định vị
                        positionDropdown(dropdown, this);
                    } else {
                        dropdown.style.display = 'none';
                    }
                });
            });

            // Reposition dropdowns on window resize
            let repositionTimer;
            window.addEventListener('resize', function() {
                clearTimeout(repositionTimer);
                repositionTimer = setTimeout(function() {
                    document.querySelectorAll('.dropdown-menu-custom[style*="display: block"]').forEach(function(dropdown) {
                        const trigger = document.querySelector('[data-dropdown="' + dropdown.id + '"]');
                        if (trigger) {
                            positionDropdown(dropdown, trigger);
                        }
                    });
                }, 200);
            });

            // Reposition dropdowns on scroll
            window.addEventListener('scroll', function() {
                document.querySelectorAll('.dropdown-menu-custom[style*="display: block"]').forEach(function(dropdown) {
                    const trigger = document.querySelector('[data-dropdown="' + dropdown.id + '"]');
                    if (trigger) {
                        positionDropdown(dropdown, trigger);
                    }
                });
            }, { passive: true });

            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown-container')) {
                    document.querySelectorAll('.dropdown-menu-custom').forEach(function(d) {
                        d.style.display = 'none';
                    });
                }
            });

            // ===== AVATAR UPLOAD =====
            const avatarInput = document.getElementById('avatarInput');
            const avatarPreview = document.getElementById('avatarPreview');

            

            

            

            

            // ===== SEARCH INPUT =====
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function(e) {
                    if (e.key === 'Enter') {
                        const query = this.value.trim();
                        if (query) {
                            console.log('Searching for:', query);
                            alert('🔍 Đang tìm kiếm: "' + query + '"');
                        }
                    }
                });
            }

        })();
