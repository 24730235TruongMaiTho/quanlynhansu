import '../../../css/nhanvien/nhanvien.css';
import { initializeEmployeePage } from './employee-page.js';

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initializeEmployeePage(), { once: true });
    } else {
        initializeEmployeePage();
    }
}
