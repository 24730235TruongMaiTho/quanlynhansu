(function() {
            'use strict';

            // Shared backend display-date contract for inline widgets.
            const qlns = window.qlns = window.qlns || {};
            const formatDisplayDate = qlns.formatDisplayDate = function (value) {
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

            const SIDEBAR_STORAGE = {
                openGroup: 'qlns.sidebar.openGroup',
                scrollTop: 'qlns.sidebar.scrollTop',
            };

            function removeSessionValue(key) {
                try { window.sessionStorage.removeItem(key); } catch (_) { /* private mode */ }
            }

            function saveSidebarState(openGroup) {
                try {
                    if (openGroup) {
                        window.sessionStorage.setItem(SIDEBAR_STORAGE.openGroup, openGroup.dataset.sidebarGroup || '');
                    } else {
                        removeSessionValue(SIDEBAR_STORAGE.openGroup);
                    }
                    const wrapper = document.querySelector('.sidebar-menu-wrapper');
                    if (wrapper) window.sessionStorage.setItem(SIDEBAR_STORAGE.scrollTop, String(wrapper.scrollTop));
                } catch (_) { /* private mode */ }
            }

            function setSubmenuState(subMenu, expanded, instant = false) {
                if (!subMenu) return;
                const initialServerState = subMenu.dataset.submenuReady === 'initial';
                const noAnimation = instant || initialServerState;
                if (noAnimation) subMenu.dataset.submenuNoAnimation = '1';
                subMenu.dataset.submenuReady = '1';
                subMenu.style.setProperty('--submenu-height', `${expanded ? subMenu.scrollHeight : 0}px`);
                subMenu.classList.toggle('open', expanded);
                if (noAnimation) {
                    requestAnimationFrame(function () {
                        delete subMenu.dataset.submenuNoAnimation;
                    });
                }
            }

            function setDropdownExpanded(dropdownId, expanded) {
                const trigger = document.querySelector(`[data-dropdown="${dropdownId}"]`);
                trigger?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }

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

                const expanded = !subMenu.classList.contains('open');
                setSubmenuState(subMenu, expanded);
                saveSidebarState(expanded ? parentItem.closest('[data-sidebar-group]') : null);
                link.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                const arrow = link.querySelector('.menu-arrow');
                if (arrow) {
                    arrow.classList.toggle('rotated');
                }

                const parentMenu = parentItem.closest('.sub-menu') || parentItem.closest('.sidebar-menu');
                if (parentMenu) {
                    const siblings = parentMenu.querySelectorAll(':scope > .nav-item > .sub-menu.open');
                    siblings.forEach(function(sibling) {
                        if (sibling !== subMenu) {
                            setSubmenuState(sibling, false);
                            const siblingArrow = sibling.closest('.nav-item').querySelector('.menu-arrow');
                            sibling.closest('.nav-item').querySelector('[data-toggle="submenu"]')?.setAttribute('aria-expanded', 'false');
                            if (siblingArrow) {
                                siblingArrow.classList.remove('rotated');
                            }
                        }
                    });
                }
                requestAnimationFrame(function() {
                    if (!expanded) setSubmenuState(subMenu, false);
                });
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
                        setSubmenuState(sub, false);
                        const arrow = sub.closest('.nav-item').querySelector('.menu-arrow');
                        sub.closest('.nav-item').querySelector('[data-toggle="submenu"]')?.setAttribute('aria-expanded', 'false');
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
                        setDropdownExpanded(d.id, false);
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

            const activeSubmenu = window.qlnsSidebarState?.findActiveSubmenu(document);
            if (activeSubmenu) {
                setSubmenuState(activeSubmenu, true, true);
                activeSubmenu.closest('[data-sidebar-group]')?.querySelector('[data-toggle="submenu"]')?.setAttribute('aria-expanded', 'true');
            }
            try {
                const openGroupId = window.sessionStorage.getItem(SIDEBAR_STORAGE.openGroup);
                const savedGroup = openGroupId ? document.querySelector(`[data-sidebar-group="${openGroupId}"]`) : null;
                const savedSubmenu = savedGroup?.querySelector('.sub-menu');
                if (savedSubmenu && !activeSubmenu) {
                    setSubmenuState(savedSubmenu, true, true);
                    savedGroup.querySelector('[data-toggle="submenu"]')?.setAttribute('aria-expanded', 'true');
                }
                const menuWrapper = document.querySelector('.sidebar-menu-wrapper');
                const savedScrollTop = Number(window.sessionStorage.getItem(SIDEBAR_STORAGE.scrollTop));
                if (menuWrapper && Number.isFinite(savedScrollTop)) menuWrapper.scrollTop = savedScrollTop;
            } catch (_) { /* private mode */ }
            window.addEventListener('pagehide', function() {
                const openGroup = document.querySelector('[data-sidebar-group] .sub-menu.open');
                saveSidebarState(openGroup ? openGroup.closest('[data-sidebar-group]') : null);
                if (!openGroup) removeSessionValue(SIDEBAR_STORAGE.openGroup);
            });

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
                            setDropdownExpanded(d.id, false);
                        }
                    });

                    // Toggle current dropdown
                    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
                        dropdown.style.display = 'block';
                        setDropdownExpanded(dropdownId, true);
                        // Reset styles trước khi định vị
                        dropdown.style.position = 'fixed';
                        dropdown.style.maxHeight = 'none';
                        dropdown.style.overflowY = 'visible';
                        
                        // Gọi hàm định vị
                        positionDropdown(dropdown, this);
                    } else {
                        dropdown.style.display = 'none';
                        setDropdownExpanded(dropdownId, false);
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
                        setDropdownExpanded(d.id, false);
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
