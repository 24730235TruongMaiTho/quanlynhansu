@extends('frontend.layout.app')

@section('title', 'Trang chủ - Quản lý nhân sự')

@push('styles')
    @vite('resources/css/frontend/home.css')
@endpush

@section('content')
    <header id="site-header" class="site-header">
        <nav class="nav-shell" id="site-nav">
            <div class="brand">
                <span>QLNS</span>
                <strong>Quản Lý Nhân Sự</strong>
            </div>
            <button class="menu-toggle" id="menuToggle" aria-label="Mở menu">☰</button>
            <div class="nav-links" id="navLinks">
                <a href="#gioi-thieu">Giới thiệu</a>
                <a href="#chuc-nang">Chức năng</a>
                <a href="#loi-ich">Lợi ích</a>
                <a href="#lien-he">Liên hệ</a>
            </div>
            <a href="#lien-he" class="btn btn-primary btn-door"><span class="door-text">Liên hệ tư vấn</span></a>
        </nav>
    </header>

    <main class="site-main">
        <section class="hero section" id="gioi-thieu">
            <div class="container">
                <p class="eyebrow">Hệ sinh thái nhân sự dành cho doanh nghiệp</p>
                <h1>Quản lý nhân sự rõ ràng, nhanh chóng, chuyên nghiệp</h1>
                <p>
                    Xây dựng quy trình nhân sự toàn diện từ hồ sơ, lương, chấm công đến nghỉ phép, giúp nhà quản lý
                    theo dõi nguồn lực tập trung trong một hệ thống duy nhất.
                </p>
                <div class="hero-actions">
                    <a href="#chuc-nang" class="btn btn-primary btn-door"><span class="door-text">Khám phá tính năng</span></a>
                    <a href="#lien-he" class="btn btn-outline">Báo giá mẫu</a>
                </div>
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <strong>99.3%</strong>
                        <span>Độ sẵn sàng hệ thống</span>
                    </div>
                    <div class="kpi-card">
                        <strong>40+</strong>
                        <span>Doanh nghiệp sử dụng</span>
                    </div>
                    <div class="kpi-card">
                        <strong>24/7</strong>
                        <span>Hỗ trợ vận hành</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="section section-line" id="chuc-nang">
            <div class="container">
                <div class="section-heading reveal" data-delay="0">
                    <p class="eyebrow">Chức năng chính</p>
                    <h2>Tập trung quản trị nhân sự theo chuỗi nghiệp vụ</h2>
                    <p>Giao diện tối giản giúp người dùng thao tác nhanh, giảm lỗi nhập liệu và dễ mở rộng về sau.</p>
                </div>
                <div class="feature-grid">
                    <article class="feature-card reveal" data-delay="80">
                        <h3>Nhân sự & Phòng ban</h3>
                        <p>Quản lý danh mục nhân viên, phòng ban, chức vụ theo sơ đồ rõ ràng, hỗ trợ tìm kiếm nhanh.</p>
                    </article>
                    <article class="feature-card reveal" data-delay="160">
                        <h3>Chấm công & Lương</h3>
                        <p>Tự động hóa quy trình chấm công, tính lương theo kỳ, dễ đối soát công bằng và minh bạch.</p>
                    </article>
                    <article class="feature-card reveal" data-delay="240">
                        <h3>Nghỉ phép & Hợp đồng</h3>
                        <p>Ghi nhận nghỉ phép, duyệt phê duyệt, lưu trữ hợp đồng theo vòng đời nhân viên.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section alt" id="loi-ich">
            <div class="container">
                <div class="section-heading reveal" data-delay="0">
                    <p class="eyebrow">Lợi ích</p>
                    <h2>Thân thiện cho cả nhân sự nội bộ và ban giám sát</h2>
                </div>
                <div class="benefit-grid">
                    <article class="reveal" data-delay="80">
                        <h3>Đối với nhân viên</h3>
                        <p>Tra cứu lịch sử lương, đơn nghỉ phép, hợp đồng và thông báo nội bộ thuận tiện.</p>
                    </article>
                    <article class="reveal" data-delay="160">
                        <h3>Đối với quản lý</h3>
                        <p>Dữ liệu tập trung theo phòng ban, theo dõi biến động nhân sự, ra quyết định nhanh hơn.</p>
                    </article>
                    <article class="reveal" data-delay="240">
                        <h3>Đối với phòng nhân sự</h3>
                        <p>Giảm thao tác thủ công, rút ngắn thời gian tổng hợp báo cáo và hạn chế sai sót tính toán.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section section-cta reveal" id="lien-he" data-delay="0">
            <div class="container">
                <h2>Bắt đầu triển khai trong 7 phút</h2>
                <p>Thiết kế nhẹ, giao diện trang trọng, phù hợp dùng cho môi trường doanh nghiệp nhỏ và vừa.</p>
                <a href="#gioi-thieu" class="btn btn-primary btn-door"><span class="door-text">Sử dụng ngay</span></a>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/home.js')
@endpush
