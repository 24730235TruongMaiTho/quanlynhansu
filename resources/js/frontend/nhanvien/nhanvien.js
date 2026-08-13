import '../../../css/nhanvien/nhanvien.css';
import { initializeEmployeeWizards } from './wizard.js';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeEmployeeWizards, { once: true });
} else {
    initializeEmployeeWizards();
}
