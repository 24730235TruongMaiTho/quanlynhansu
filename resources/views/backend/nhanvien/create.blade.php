@extends('backend.layout.app')
@section('title', 'Thêm nhân viên - Quản lý nhân sự')
@section('content')
<div class="content-area">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="bi bi-person-plus-fill text-danger me-2"></i>Thêm nhân viên mới</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="#">Nhân sự</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Thêm nhân viên</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="#" class="btn btn-outline-secondary" style="display: inline-block; padding: 10px 20px; background: #f8f9fa; border: 1.5px solid #e0e0e0; border-radius: 10px; color: #495057; text-decoration: none; transition: all 0.2s;">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
    </div>

    <!-- Form -->
    <div class="employee-form-wrapper">
        <form id="employeeForm" novalidate>
            <!-- Thông tin cá nhân -->
            <div class="section-title">
                <i class="bi bi-person-circle"></i>Thông tin cá nhân
            </div>
            <div class="row g-4" style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
                    <div class="avatar-upload">
                        <div class="avatar-preview" id="avatarPreview">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <label class="btn-upload" for="avatarInput">
                            <i class="bi bi-camera me-2"></i>Tải ảnh đại diện
                        </label>
                        <input type="file" id="avatarInput" accept="image/*" style="display: none;">
                        <small class="text-muted">Hỗ trợ JPG, PNG, GIF (tối đa 2MB)</small>
                    </div>
                    <div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label for="fullName" class="form-label">
                                    Họ và tên <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="fullName" 
                                        placeholder="Nhập họ và tên" required>
                                <div class="invalid-feedback">Vui lòng nhập họ và tên</div>
                            </div>
                            <div>
                                <label for="dob" class="form-label">
                                    Ngày sinh <span class="required">*</span>
                                </label>
                                <input type="date" class="form-control" id="dob" required>
                                <div class="invalid-feedback">Vui lòng chọn ngày sinh</div>
                            </div>
                            <div>
                                <label for="gender" class="form-label">
                                    Giới tính <span class="required">*</span>
                                </label>
                                <select class="form-select" id="gender" required>
                                    <option value="">Chọn giới tính</option>
                                    <option value="male">Nam</option>
                                    <option value="female">Nữ</option>
                                    <option value="other">Khác</option>
                                </select>
                                <div class="invalid-feedback">Vui lòng chọn giới tính</div>
                            </div>
                            <div>
                                <label for="nationality" class="form-label">
                                    Quốc tịch <span class="required">*</span>
                                </label>
                                <select class="form-select" id="nationality" required>
                                    <option value="">Chọn quốc tịch</option>
                                    <option value="vn">Việt Nam</option>
                                    <option value="us">Hoa Kỳ</option>
                                    <option value="jp">Nhật Bản</option>
                                    <option value="kr">Hàn Quốc</option>
                                    <option value="cn">Trung Quốc</option>
                                </select>
                                <div class="invalid-feedback">Vui lòng chọn quốc tịch</div>
                            </div>
                            <div>
                                <label for="idCard" class="form-label">
                                    Số CMND/CCCD <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="idCard" 
                                        placeholder="Nhập số CMND/CCCD" required maxlength="12">
                                <div class="invalid-feedback">Vui lòng nhập số CMND/CCCD</div>
                            </div>
                            <div>
                                <label for="issueDate" class="form-label">
                                    Ngày cấp <span class="required">*</span>
                                </label>
                                <input type="date" class="form-control" id="issueDate" required>
                                <div class="invalid-feedback">Vui lòng chọn ngày cấp</div>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label for="issuePlace" class="form-label">
                                    Nơi cấp <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="issuePlace" 
                                        placeholder="Nhập nơi cấp CMND/CCCD" required>
                                <div class="invalid-feedback">Vui lòng nhập nơi cấp</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <!-- Thông tin liên hệ -->
            <div class="section-title">
                <i class="bi bi-envelope-paper"></i>Thông tin liên hệ
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <label for="email" class="form-label">
                        Email <span class="required">*</span>
                    </label>
                    <input type="email" class="form-control" id="email" 
                            placeholder="example@company.com" required>
                    <div class="invalid-feedback">Vui lòng nhập email hợp lệ</div>
                </div>
                <div>
                    <label for="phone" class="form-label">
                        Số điện thoại <span class="required">*</span>
                    </label>
                    <input type="tel" class="form-control" id="phone" 
                            placeholder="Nhập số điện thoại" required>
                    <div class="invalid-feedback">Vui lòng nhập số điện thoại</div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <label for="address" class="form-label">
                        Địa chỉ thường trú <span class="required">*</span>
                    </label>
                    <input type="text" class="form-control" id="address" 
                            placeholder="Nhập địa chỉ thường trú" required>
                    <div class="invalid-feedback">Vui lòng nhập địa chỉ</div>
                </div>
                <div>
                    <label for="province" class="form-label">
                        Tỉnh/Thành phố <span class="required">*</span>
                    </label>
                    <select class="form-select" id="province" required>
                        <option value="">Chọn tỉnh/thành</option>
                        <option value="hanoi">Hà Nội</option>
                        <option value="hcm">TP. Hồ Chí Minh</option>
                        <option value="danang">Đà Nẵng</option>
                        <option value="haiphong">Hải Phòng</option>
                        <option value="cantho">Cần Thơ</option>
                    </select>
                    <div class="invalid-feedback">Vui lòng chọn tỉnh/thành phố</div>
                </div>
                <div>
                    <label for="district" class="form-label">
                        Quận/Huyện <span class="required">*</span>
                    </label>
                    <select class="form-select" id="district" required>
                        <option value="">Chọn quận/huyện</option>
                        <option value="1">Quận 1</option>
                        <option value="2">Quận 2</option>
                        <option value="3">Quận 3</option>
                    </select>
                    <div class="invalid-feedback">Vui lòng chọn quận/huyện</div>
                </div>
                <div>
                    <label for="ward" class="form-label">
                        Phường/Xã <span class="required">*</span>
                    </label>
                    <select class="form-select" id="ward" required>
                        <option value="">Chọn phường/xã</option>
                        <option value="1">Phường 1</option>
                        <option value="2">Phường 2</option>
                        <option value="3">Phường 3</option>
                    </select>
                    <div class="invalid-feedback">Vui lòng chọn phường/xã</div>
                </div>
            </div>

            <hr class="my-4">

            <!-- Thông tin công việc -->
            <div class="section-title">
                <i class="bi bi-briefcase-fill"></i>Thông tin công việc
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                <div>
                    <label for="employeeId" class="form-label">
                        Mã nhân viên <span class="required">*</span>
                    </label>
                    <input type="text" class="form-control" id="employeeId" 
                            placeholder="VD: EMP001" required>
                    <div class="invalid-feedback">Vui lòng nhập mã nhân viên</div>
                </div>
                <div>
                    <label for="department" class="form-label">
                        Phòng ban <span class="required">*</span>
                    </label>
                    <select class="form-select" id="department" required>
                        <option value="">Chọn phòng ban</option>
                        <option value="it">Phòng Kỹ thuật</option>
                        <option value="sales">Phòng Kinh doanh</option>
                        <option value="hr">Phòng Nhân sự</option>
                        <option value="finance">Phòng Tài chính</option>
                        <option value="marketing">Phòng Marketing</option>
                    </select>
                    <div class="invalid-feedback">Vui lòng chọn phòng ban</div>
                </div>
                <div>
                    <label for="position" class="form-label">
                        Chức vụ <span class="required">*</span>
                    </label>
                    <select class="form-select" id="position" required>
                        <option value="">Chọn chức vụ</option>
                        <option value="manager">Trưởng phòng</option>
                        <option value="staff">Nhân viên</option>
                        <option value="intern">Thực tập sinh</option>
                        <option value="director">Giám đốc</option>
                    </select>
                    <div class="invalid-feedback">Vui lòng chọn chức vụ</div>
                </div>
                <div>
                    <label for="startDate" class="form-label">
                        Ngày vào làm <span class="required">*</span>
                    </label>
                    <input type="date" class="form-control" id="startDate" required>
                    <div class="invalid-feedback">Vui lòng chọn ngày vào làm</div>
                </div>
                <div>
                    <label for="contractType" class="form-label">
                        Loại hợp đồng <span class="required">*</span>
                    </label>
                    <select class="form-select" id="contractType" required>
                        <option value="">Chọn loại hợp đồng</option>
                        <option value="indefinite">Không xác định thời hạn</option>
                        <option value="fixed">Xác định thời hạn</option>
                        <option value="probation">Thử việc</option>
                        <option value="parttime">Bán thời gian</option>
                    </select>
                    <div class="invalid-feedback">Vui lòng chọn loại hợp đồng</div>
                </div>
                <div>
                    <label for="salary" class="form-label">
                        Mức lương (VNĐ) <span class="required">*</span>
                    </label>
                    <input type="number" class="form-control" id="salary" 
                            placeholder="Nhập mức lương" required min="0">
                    <div class="invalid-feedback">Vui lòng nhập mức lương hợp lệ</div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <label for="note" class="form-label">Ghi chú</label>
                    <textarea class="form-control" id="note" rows="3" 
                                placeholder="Nhập ghi chú (nếu có)"></textarea>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="reset" class="btn btn-secondary">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>Nhập lại
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-2"></i>Lưu thông tin
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('styles')

@endpush
@push('scripts')
<script>
(function(){
// ===== FORM VALIDATION =====
            const form = document.getElementById('employeeForm');

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const inputs = form.querySelectorAll('.form-control, .form-select');
                inputs.forEach(function(input) {
                    input.classList.remove('is-invalid');
                });

                let isValid = true;

                inputs.forEach(function(input) {
                    if (input.hasAttribute('required') && !input.value.trim()) {
                        input.classList.add('is-invalid');
                        isValid = false;
                    }
                    if (input.type === 'email' && input.value.trim()) {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(input.value.trim())) {
                            input.classList.add('is-invalid');
                            isValid = false;
                        }
                    }
                });

                if (isValid) {
                    const formData = {
                        fullName: document.getElementById('fullName').value,
                        dob: document.getElementById('dob').value,
                        gender: document.getElementById('gender').value,
                        nationality: document.getElementById('nationality').value,
                        idCard: document.getElementById('idCard').value,
                        issueDate: document.getElementById('issueDate').value,
                        issuePlace: document.getElementById('issuePlace').value,
                        email: document.getElementById('email').value,
                        phone: document.getElementById('phone').value,
                        address: document.getElementById('address').value,
                        province: document.getElementById('province').value,
                        district: document.getElementById('district').value,
                        ward: document.getElementById('ward').value,
                        employeeId: document.getElementById('employeeId').value,
                        department: document.getElementById('department').value,
                        position: document.getElementById('position').value,
                        startDate: document.getElementById('startDate').value,
                        contractType: document.getElementById('contractType').value,
                        salary: document.getElementById('salary').value,
                        note: document.getElementById('note').value
                    };

                    console.log('Form Data:', formData);
                    alert('✅ Thêm nhân viên thành công!');
                    form.reset();
                    avatarPreview.innerHTML = '<i class="bi bi-person-circle"></i>';
                    avatarInput.value = '';
                } else {
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                    }
                    alert('⚠️ Vui lòng kiểm tra lại các trường thông tin bắt buộc!');
                }
            });

            form.querySelectorAll('.form-control, .form-select').forEach(function(input) {
                input.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                });
                input.addEventListener('change', function() {
                    this.classList.remove('is-invalid');
                });
            });

            // ===== SALARY FORMAT =====
            const salaryInput = document.getElementById('salary');
            salaryInput.addEventListener('blur', function() {
                if (this.value) {
                    const num = parseInt(this.value.replace(/,/g, ''));
                    if (!isNaN(num)) {
                        this.value = num.toLocaleString('vi-VN');
                    }
                }
            });
            salaryInput.addEventListener('focus', function() {
                if (this.value) {
                    this.value = this.value.replace(/,/g, '');
                }
            });
});
</script>
@endpush