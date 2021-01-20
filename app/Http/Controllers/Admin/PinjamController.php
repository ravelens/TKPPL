<?php

namespace App\Http\Controllers\Admin;

use App\Pinjam;
use App\Buku;
use App\Peraturan;
use DataTables;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Anggota;
use App\Petugas;
use Illuminate\Support\Facades\Auth;
use App\DetailPinjam;
use Carbon\Carbon;
use Validator;
use DB;

class PinjamController extends Controller
{

    public function json()
    {
        return DataTables::of(Pinjam::status('dipinjam')->orderBy('tgl_pinjam', 'desc')->get())
        ->addColumn('no', function() {
            return '';
        })
        ->addColumn('anggota', function(Pinjam $pinjam) {
            $anggota = Anggota::where('id',$pinjam->anggota_id)->first();
            return "<a class='text-green' href='". route('anggota.show', $anggota->id) ."?rb=sd'>{$anggota->nama}</a>";
        })
        ->addColumn('buku', function(Pinjam $pinjam) {
            $return = '';
            $detailPinjam = DetailPinjam::where('pinjam_id',$pinjam->id)->get();
            foreach ($detailPinjam as $value) {
                $buku = Buku::find($value->buku_id);
                $return .= "<p><a class='badge' href='" .route('buku.show',$buku->id). "'>{$buku->judul}</a></p> "; 
            }
            return "$return";
        })
        ->addColumn('petugas', function(Pinjam $pinjam) {
            $petugas = Petugas::where('user_id',$pinjam->petugas_id)->first();
            return "<a class='text-green' href='". route('petugas.show', $petugas->id) ."'>{$petugas->nama}</a>";
        })
        ->addColumn('opsi', function(Pinjam $pinjam) {
            return "
            <button title='hapus' class='btn btn-danger hapus-pinjam' type='button' data-id='$pinjam->id'><i class='fa fa-trash'></i></button>
            <a title='detail' href='". route('pinjam.show', $pinjam->id) ."' class='btn btn-dark' data-id='$pinjam->id'><i class='fa fa-eye'></i> </a>";
        })
        ->rawColumns(['opsi', 'anggota', 'petugas', 'buku'])
        ->make(true);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('pinjam.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $data['anggota'] = Anggota::all();
        $data['buku'] = Buku::all();
        $data['peraturan'] = Peraturan::active()->first();
        return view('pinjam.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
			'anggota' => ['required','numeric','exists:anggota,id'],
            'buku' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false , 'errors'=>$validator], 401);    

        }

        // cek apakah id buku yang dikirm berupa array
        $arrBuku = json_decode($request->buku);
        $arrBuku = array_unique($arrBuku);
        if(! is_array($arrBuku) OR empty($arrBuku))
        {
            $response = [
                'status' => false,
                'errors' => 'Buku yang dipilih tidak valid, silahkan refresh halaman!'
            ];
            return response()->json($response);
        }

        // cek apakah id buku tersedia di table 
        foreach ($arrBuku as $value) {
            if(! $buku = Buku::find($value))
            {
                $response = [
                    'status' => false,
                    'errors' => 'Buku yang anda masukkan tidak ditemukan lagi'
                ];
                return response()->json($response);
            }
        }

        // peraturan aktif
        $peraturan = Peraturan::where('status','aktif')->first();
        // ambil semua data
        $anggota = Anggota::find($request->anggota);
        // cek jumlah peminjaman anggota < dari maksimal pemminjaman di table peraturan
        if (count($arrBuku) > $peraturan->maksimal_peminjaman)
        {
            $response = [
                'status' => false,
                'errors' => 'maks peminjaman',
                'data' => [
                    'anggota' => $anggota->nama,
                    'anggotaId' => $anggota->id,
                    'pinjaman' => 0,
                    'dipinjam' => count($arrBuku),
                    'maksimal' => $peraturan->maksimal_peminjaman,
                ]
            ];
            return response()->json($response);
        }
        

        // cek jumlah peminjaman anggota yang belum dikembalikan < dari maksimal pemminjaman di table peraturan
        $peminjamanAnggota = Pinjam::where(['anggota_id' => 9, 'status' => 'dipinjam'])->withCount('detailPinjam')->get();
        if(! $peminjamanAnggota->isEmpty())
        {
            $peminjamanAnggotaSebelumnya = 0;
            foreach ($peminjamanAnggota as $item) {
                $peminjamanAnggotaSebelumnya += $item->detail_pinjam_count;
            }
            $peminjamanAnggotaBaru = count($arrBuku);
            $totalPeminjamanAnggota = $peminjamanAnggotaSebelumnya + $peminjamanAnggotaBaru;
            return response()->json(['status' => false , "errors" => $totalPeminjamanAnggota]);
            if($totalPeminjamanAnggota > $peraturan->maksimal_peminjaman)
            {
                $response = [
                    'status' => false,
                    'errors' => 'maks peminjaman',
                    'data' => [
                        'anggota' => $anggota->nama,
                        'anggotaId' => $anggota->id,
                        'pinjaman' => $peminjamanAnggotaSebelumnya,
                        'dipinjam' => $peminjamanAnggotaBaru,
                        'maksimal' => $peraturan->maksimal_peminjaman,
                    ]
                ];
                return response()->json($response);
            }
        }
        
        // sukses validasi
        $pinjam = Pinjam::create([
            'anggota_id' => $request->anggota,
            'status' => 'dipinjam',
            'tgl_pinjam' => now(),
            'peraturan_id' => $peraturan->id,
            'petugas_id' => Auth::user()->id,
        ]);

        // detail pinjam
        $bukuRecord = [];
        foreach ($arrBuku as $b) {
            $bukuRecord[] = [
                'pinjam_id' => $pinjam->id,
                'buku_id' => $b
            ];
        }
        DetailPinjam::insert($bukuRecord);
        $response = [
            'status' => true,
            'data' => $pinjam
        ];

        return response()->json($response);        
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Pinjam  $pinjam
     * @return \Illuminate\Http\Response
     */
    public function show(Pinjam $pinjam)
    {
        $data['pinjam'] = $pinjam;
        $data['anggota'] = Pinjam::join('anggota as a','a.id','pinjam.anggota_id')->first();
        $data['petugas'] = Pinjam::join('petugas as p','p.user_id','pinjam.petugas_id')->first();
        return view('pinjam.detail', $data);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Pinjam  $pinjam
     * @return \Illuminate\Http\Response
     */
    public function destroy(Pinjam $pinjam)
    {
        $delete = $pinjam->delete();
        $response = $delete ? ['status' => true, 'msg' => 'Data berhasil dihapus', 'data' => $pinjam] : ['status' => false, 'msg' => 'Data gagal dihapus'];
        return response()->json($response);
    }

    public function getListBook($bukuId, $anggotaId = null)
    {

        $buku = Buku::find($bukuId);
        if(! $buku)
        {
            $response = [
                'status' => false,
                'msg' => 'tidak ada buku'
            ];
            return response()->json($response);
        }
        $data = [
            'judul' => $buku->judul,
            'penerbit' => $buku->penerbit->nama,
            'kategori' => $buku->kategori->nama,
            'pengarang' => $buku->pengarang->nama,
            'cover' => asset('img/buku') ."/" . getPicture($buku->cover, 'no-pict.png')
        ];
        $response = [
            'status' => true,
            'data' => $data
        ];
        return response()->json($response);
    }

    public function getByAnggotaId($id)
    {
        $anggota = Anggota::where('id',$id)->first();
        $nama_ag = $anggota->nama;
        $id_ag = $anggota->id;
        $petugas = Petugas::join('pinjam as p','p.petugas_id','petugas.user_id')->where('p.anggota_id',$id)->where('status','dipinjam')->first();
        $nama_pg = $petugas->nama;
        $id_pg = $petugas->petugas_id;
        $peminjaman = Pinjam::where('status','dipinjam')->where('anggota_id',$id)->first();
        
        if (!$anggota) 
        {
            $response = [
                'status' => false,
                'msg' => "Data anggota tidak ditemukan"
            ];
            return response()->json($response);
        }
        if (!$peminjaman) 
        {
            $response = [
                'status' => false,
                'msg' => "{$anggota->nama} tidak memiliki peminjaman buku"
            ];
            return response()->json($response);
        }
        $bukuPinjaman = [];
        $detailPinjam = DetailPinjam::join('pinjam as p','p.id','detail_pinjam.pinjam_id')->where('status','dipinjam')->where('p.anggota_id',$id)->get();
        foreach ($detailPinjam as $value) {
            $key = $value->id;
            $id_buku = $value->buku_id;
            // $judul = $buku->isbn;
            $pengarang = Buku::join('pengarang as p','p.id','buku.pengarang_id')->where('buku.id',$id_buku)->first();
            $buku = Buku::join('pengarang as p','p.id','buku.pengarang_id')->first();
            $peng_nama = $pengarang->nama;
            $buku_nama = $buku->judul;
            $buku_cover = $buku->cover;
            
            $bukuPinjaman[$key]['id'] = $buku->id;
            $bukuPinjaman[$key]['judul'] = $buku_nama;
            $bukuPinjaman[$key]['cover'] = asset('img/buku') ."/". getPicture($buku_cover, 'no-pict.png');
            $bukuPinjaman[$key]['pengarang'] = $peng_nama;
        }
        // return response()->json(['status' => false , "msg" => $id]);
        
        // dd($bukuPinjaman);
        // $tanggal = $peminjaman->tgl_pinjam;
        // return response()->json(['status' => false , "msg" => $tanggal]);

        $response = [
            'status' => true,
            'data' => [
                'anggota' => [
                    'id' => $id_ag,
                    'nama' => $nama_ag
                ],
                'buku' => $bukuPinjaman,
                'pinjam' => [
                    'id' => $peminjaman->id,
                    'tgl_pinjam' => $peminjaman->tgl_pinjam->format('Y-m-d h:i:s'),
                ],
                'petugas' => [
                    'id' =>  $id_pg,
                    'nama' =>  $nama_pg,
                ]
            ]
        ];

        return response()->json($response);
    }

}
