<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportesController extends Controller
{
    public function __construct(){
        if(!\Auth::check()){
            return  redirect('/');
        }
    }

    public function ReporteVentas(Request $request)
    {
        $Anio = $request->anio;
        $Idtercero = $request->Idtercero;
        if($Anio <=0){
            $Anio = 2019;
        }
        if($Idtercero <=0){
            $Idtercero = \Auth::user()->IdTercero;
        }
        $Ventas = DB::table("sql_ventas_general")->select(
                    DB::raw('MONTHNAME(FchAut) as Mes'),
                    DB::raw('YEAR(FchAut) as Anio'),
                    DB::raw('SUM(SubTotal) as Total'),
                    DB::raw('MONTH(FchAut) as Mes2')
        )->whereYear('FchAut',$Anio)->where('NitCte',$Idtercero)->groupBy(DB::raw('MONTHNAME(FchAut)'),DB::raw('YEAR(FchAut)'),DB::raw('MONTH(FchAut)'))->OrderBy(DB::raw('MONTH(FchAut)'))->get();
        return[
            'ventas'=>$Ventas
        ];
    }
}
