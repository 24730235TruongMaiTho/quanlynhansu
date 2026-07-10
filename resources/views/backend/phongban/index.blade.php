@extends('backend.layout.app');
@section('title', 'Quản lý phòng ban');
@section('content');
<div class="row">
    <div class="col-12">
        <h2>Danh sách phòng ban</h2>
        <table>
            <tr>
            <th>Mã phòng ban</th> 
            <th>Tên phòng ban</th>
        @foreach($pb in $phongban)
            <p>{{$pb->ten_pb}}</p>
        @endforeach
    </div>
</div>