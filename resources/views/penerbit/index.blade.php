@extends('layouts.master')

@section('title', 'Daftar penerbit')

@section('styles')
<link href="{{ asset('admin/assets/') }}/vendors/datatables.net-bs/css/dataTables.bootstrap.min.css" rel="stylesheet">
@endsection

@section('title.left')
<h3>Penerbit</h3>
@stop

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="x_panel">
            <div class="x_title">
                <h2>Daftar data<small>penerbit</small></h2>
                <div class="clearfix"></div>
            </div>
            <div class="x_content">
                <div id="datatable_wrapper" class="dataTables_wrapper form-inline dt-bootstrap no-footer">
                    <div class="row">
                        <div class="col-sm-12">
                            <table id="datatable-server-side" class="table table-striped table-bordered dataTable no-footer"
                                role="grid" aria-describedby="datatable_info">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nama penerbit</th>
                                        <th>Gambar</th>
                                        <th>Jumlah buku</th>
                                        <th>Opsi</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-md-4">
        <div class="x_panel">
            <div class="x_title">
                <h2>Form tambah</h2>
                <div class="clearfix"></div>
            </div>
            <div class="x_content">
                <form action="{{ route('penerbit.store') }}" id="form-tambah" method="post" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="nama_new">Nama penerbit <span class="text-danger">*</span> </label>
                    <input id="nama_new" autocomplete="off" value="{{ old('nama') }}" type="text" name="nama_new" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="foto_new">Foto</label>
                    <input id="foto_new" type="file" name="foto_new" class="form-control">
                </div>
                <div class="form-group">
                    <button class="btn btn-dark" type="submit">Tambah</button>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Small modal -->

<div class="modal fade bs-example-modal-sm" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" id="form-ubah" action="" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" id="id">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">×</span>
                    </button>
                    <h4 class="modal-title" id="myModalLabel2">Ubah data penerbit</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nama">Nama penerbit <span class="text-danger">*</span> </label>
                        <input id="nama" autocomplete="off" type="text" name="nama" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="foto">Foto</label>
                        <input id="foto" type="file" name="foto" class="form-control">
                        <span class="small">Kosongkan jika tidak ingin mengubah foto</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-dark">Simpan perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- /modals -->
@endsection

@section('scripts')
<script src="{{ asset('admin/assets') }}/vendors/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="{{ asset('admin/assets') }}/vendors/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>
<script>
    let elementTable = $('#datatable-server-side');
    let table = elementTable.DataTable({
        processing: true,
        serverSide: true,
        ajax: siteUrl('penerbit/json'),
        columns: [
            { data: 'id', name: 'id', searchable: false },
            { data: 'nama', name: 'nama' },
            { data: 'gambar', name: 'gambar', searchable: false, orderable: false },
            { data: 'buku_count', name: 'buku_count' },
            { data: 'opsi', name: 'opsi', searchable: false, orderable: false },
        ]
    });
    
    async function getPenerbit(id){
        try {
            const response = await axios.get(siteUrl(`penerbit/${id}`));
            if (!response.data.status) return alert('Data tidak ditemukan');
            const {nama} = response.data.data;
            $('.modal #nama').val(nama);
            $('#id').val(id);
        } catch (error) {
            console.error(error);
        }
    }

    elementTable.on('click', '.hapus-penerbit' ,function(e) {
        e.preventDefault();
        let id = $(this).data('id');
        let url = `penerbit/${id}`;
        let ini = $(this);
        alertify.confirm('Konfirmasi',"Yakin ingin menghapus data ini?",
        function(){
            hapusData(url, ini);
        }, function() {
        });
    })

    elementTable.on('click', '.ubah-penerbit', function(e) {
        e.preventDefault();
        let id = $(this).data('id');
        $('.modal form').attr('action', siteUrl(`penerbit/${id}`));
        getPenerbit(id);
    })

    $('#form-tambah').on('submit', function(e) {
        e.preventDefault();
        let data = new FormData(this);
        sendTambah(data)
        $('#form-tambah button').addClass('disabled');
        $('#form-tambah button').html('Memproses...');
    })

    async function sendTambah(data) {
        try {
            const response = await axios({
                method: 'POST',
                url: siteUrl('penerbit'),
                data
            })
            $('#form-tambah button').removeClass('disabled');
            $('#form-tambah button').html('Tambah');
            let elNama = $('#form-tambah #nama');
            let elFoto = $('#form-tambah #foto');
            if (!response.data.status) {
                const {nama, foto} = response.data.errors                
                clearErrorInput(elNama);
                clearErrorInput(elFoto);
                if (nama) {
                    elNama.addClass('parsley-error');
                    elNama.after(`<b class="text-danger errorInput">${nama}</b>`);
                }
                if (foto) {
                    elFoto.addClass('parsley-error');
                    elFoto.after(`<b class="text-danger errorInput">${foto}</b>`);
                }
                return;
            }
            elNama.val('');
            clearErrorInput(elNama);
            clearErrorInput(elFoto);
            initNotify('Data berhasil ditambah');
            table.ajax.reload();
        } catch (error) {
            alert(error);
        }
    }

    $('#form-ubah').on('submit', function(e) {
        e.preventDefault();
        let id = $('#id').val();
        let data = new FormData(this);
        $('#form-ubah button[type="submit"]').addClass('disabled');
        $('#form-ubah button[type="submit"]').html('Memproses...');        
        sendUbah(data, id);       
    })

    async function sendUbah(data, id) {
        try {
            const response = await axios({
                method: 'POST',
                url: siteUrl(`penerbit/${id}`),
                data
            })
            $('#form-ubah button[type="submit"]').removeClass('disabled');
            $('#form-ubah button[type="submit"]').html('Simpan perubahan');
            let elNama = $('#form-ubah #nama');
            let elFoto = $('#form-ubah #foto');
            if (!response.data.status) {
                const {nama, foto} = response.data.errors                
                clearErrorInput(elNama);
                clearErrorInput(elFoto);
                if (nama) {
                    elNama.addClass('parsley-error');
                    elNama.after(`<b class="text-danger errorInput">${nama}</b>`);
                }
                if (foto) {
                    elFoto.addClass('parsley-error');
                    elFoto.after(`<b class="text-danger errorInput">${foto}</b>`);
                }
                return;
            }
            $('.modal').modal('hide');
            elNama.val('');
            clearErrorInput(elNama);
            clearErrorInput(elFoto);
            initNotify('Data berhasil diubah');
            table.ajax.reload();
        } catch (error) {
            alert(error);
        }
    }

</script>
@endsection
