<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Pinjam;
use DataTables;
use App\Buku;
use App\Anggota;
use App\Http\Controllers\Controller;
use App\DetailPinjam;
use Carbon\Carbon;
use App\Peraturan;
use Illuminate\Support\Facades\Auth;
use App\Pengembalian;
use App\DetailPengembalian;

class PengembalianController extends Controller
{

    public function json()
    {
        return DataTables::of(Pinjam::status('dikembalikan')->get())
        ->addColumn('no', function() {
            return '';
        })
        ->addColumn('tgl_kembali', function(Pinjam $pinjam) {
            $pengembalian = Pengembalian::where('pinjam_id',$pinjam->id)->first();
            return "{$pengembalian->tgl_pengembalian}";
        })
        ->addColumn('anggota', function(Pinjam $pinjam) {
            $anggota = Pinjam::join('anggota as a','pinjam.anggota_id','a.id')->first();
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
            $petugas = Pinjam::join('petugas as p','p.user_id','pinjam.petugas_id')->first();
            return "<a class='text-green' href='". route('petugas.show', $petugas->id) ."'>{$petugas->nama}</a>";
        })
        ->addColumn('opsi', function(Pinjam $pinjam) {
            return "
            <button title='hapus' class='btn btn-danger hapus-pengembalian' type='button' data-id='$pinjam->id'><i class='fa fa-trash'></i></button>
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
        return view('pengembalian.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $data['anggota'] = Anggota::all();
        return view('pengembalian.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'anggota_id' => 'required|exists:pinjam|numeric',
            'buku' => 'required',
            'pinjam_id' => 'required|exists:pinjam,id|numeric'
        ]);
        if ($validator->fails()) {
            $response = [
                'status' => false,
                'errors' => "Data tidak valid"
            ];
            return response()->json($response);
        }
        $arrBuku = json_decode($request->buku);
        
        $jmlPengembalian = count($arrBuku);
        // return response()->json(['status'=>false,'errors'=>$request->all()]);
        // update table pinjam
        $where = ['id' => $request->pinjam_id, 'anggota_id' => $request->anggota_id, 'status' => 'dipinjam'];
        $pinjam = Pinjam::where($where)->first();
        if (!$pinjam) {
            $response = [
                'status' => false,
                'errors' => 'pengembalian tidak valid'
            ];
            return response()->json($response);
        }
        // cek jika ada denda
        $peraturan = Peraturan::find($pinjam->peraturan_id);
        $maksPengemblian = $peraturan->lama_pengembalian;
        $biayaDenda = $peraturan->biaya_denda;
        $tglPeminjaman = $pinjam->tgl_pinjam;
        $tglPengembalian = Carbon::now();
        $selisihTgl = $tglPeminjaman->diffInDays($tglPengembalian);
        
        $pinjam['status'] = 'dikembalikan';
        $pinjam['tgl_kembali'] = $tglPengembalian;
        $pinjam['petugas_id'] = 1;
        // detail pengembalian buku
        $newArrBuku = [];
        $key = 0;
        foreach ($arrBuku as $buku) {
            $data_buku = DetailPinjam::join('buku as b','b.id','detail_pinjam.buku_id')->first();
            $kondisi = ['pinjam_id' => $pinjam->id, 'buku_id' => $data_buku->id];

            $update = ['keterangan' => $buku->keterangan];

            $newArrBuku[$key]['judul'] = $data_buku->judul; 
            $newArrBuku[$key]['ket'] = $buku->keterangan;
            $id_pinjam = $pinjam->id;
            $user_id = Auth::user()->id;
            Pinjam::where('id',$pinjam->id)->update([
                'status' => 'dikembalikan',
            ]);
            $pengembalian = Pengembalian::create([
                'pinjam_id' => $pinjam->id,
                'petugas_id' => Auth::user()->id,
                'tgl_pengembalian' => now(),
            ]);
            // return response()->json(['status'=>false,'errors' => Auth::user()->id]);
            // Pinjam::where('')
            $key += 1;
        }
        
        $anggota = Anggota::find($request->anggota_id);
        // return response()->json(['status'=>false,'errors'=>$buku->keterangan]);
        $response = [
            'status' => true,
            'data' => [
                'anggota' => $anggota->nama,
                'tglPinjam' => $tglPeminjaman->format('Y-m-d H:i:s'),
                'tglKembali' => $tglPengembalian->format('Y-m-d H:i:s'),
                'buku' => $newArrBuku,
                'jmlPengembalian' => $jmlPengembalian,
                'perpustakaan' => [
                    'nama' => identitas()->nama,
                    'alamat' => identitas()->alamat
                ]
            ]
        ];
        if ($selisihTgl > $maksPengemblian)
        {
            $keterlambatan = $selisihTgl - $maksPengemblian;
            $subTotalDendaKeterlambatan = $keterlambatan * $biayaDenda;
            $grandTotalDendaKetlambatan =  $subTotalDendaKeterlambatan * $jmlPengembalian;
            $response['data']['denda'] = [
                'maks' => $maksPengemblian,
                'keterlambatan' => $keterlambatan,
                'biayaDenda' => rupiah($biayaDenda,''),
                'subtotalketerlambatan' => rupiah($subTotalDendaKeterlambatan,''),
                'grandtotalketerlambatan' => rupiah($grandTotalDendaKetlambatan,'')
            ];
            return response()->json($response);
        }
        return response()->json($response);
    }
    

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $pengemalian = Pinjam::find($id);
        $delete = $pengemalian->delete();
        $response = $delete ? ['status' => true, 'msg' => 'Data berhasil dihapus', 'data' => ''] : ['status' => false, 'msg' => 'Data gagal dihapus'];
        return response()->json($response);
    }
}
