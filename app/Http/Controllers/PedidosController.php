<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Notifications\NotifyKasten;
use App\Movimientos;
use App\MovimientosDet;
use App\Terceros;
use App\Documentos;
use App\Item;
use App\Mail\EnviarCorreo;
use App\Asesores;
use App\User;


class PedidosController extends Controller
{
    public function __construct()
    {
        if(!\Auth::check()){
           return  redirect('/login');
        }
    }

    public function index(Request $request){
        $IdTercero =  \Auth::user()->IdTercero;
        $criterio = $request->criterio;
        $busqueda = $request->buscar;
        $Pedidos = Movimientos::select('IdMovimiento','NroDocumento','Fecha','Soporte','Fecha1','Fecha2','Total','Estado','movimientos.Comentarios',DB::raw("concat(NmDireccion,'-',NmTipoDireccion) as NmDireccion"),'asesores.Nombre as Asesor','NombreCorto')
        ->leftjoin('direcciones','movimientos.IdDireccion','=','direcciones.IdDireccion')
        ->leftjoin('tipos_direcciones','tipos_direcciones.IdTipoDireccion','=','direcciones.Tipo')
        ->leftjoin('terceros','terceros.IdTercero','=','movimientos.IdTercero')
        ->leftjoin('asesores','asesores.IdAsesor','=','movimientos.IdAsesor');
        //->where('movimientos.IdTercero','=', $IdTercero);
        if($criterio !='' && $busqueda !=''){
            $Pedidos = $Pedidos->where('movimientos.'.$criterio,'like','%'.$busqueda.'%');
        }
        $Pedidos = $Pedidos->where('IdDocumento','=','61')->orderBy('movimientos.Fecha','DESC')->paginate(10);

        return [
            'pagination' => [
                'total'        => $Pedidos->total(),
                'current_page' => $Pedidos->currentPage(),
                'per_page'     => $Pedidos->perPage(),
                'last_page'    => $Pedidos->lastPage(),
                'from'         => $Pedidos->firstItem(),
                'to'           => $Pedidos->lastItem(),
            ],
            'pedidos' => $Pedidos
        ];
    }

    public function store(Request $request){
        try{
            DB::beginTransaction();
            $Doc =  Documentos::findOrFail('61');
            if($request->idtercero >0){
                $Tercero = Terceros::findOrFail($request->idtercero);
            }
            else{
                $Tercero = Terceros::findOrFail(\Auth::user()->IdTercero);
            }
            
            $IdTercero = $Tercero->IdTercero ;
            $arMovimiento = new  Movimientos;
            $arMovimiento->IdDocumento = 61;
            $arMovimiento->TpDocumento = 20;
            $arMovimiento->Fecha = date('Y-m-d H:i:s');
            $arMovimiento->Fecha1 = $request->fecha_minima;
            $arMovimiento->Fecha2 = $request->fecha_maxima;
            $arMovimiento->IdTercero = $IdTercero;
            $arMovimiento->IdFormaPago = $Tercero->IdFormaPago;
            $arMovimiento->IdDireccion = $request->id_direccion;
            $arMovimiento->IdAsesor =  $Tercero->IdAsesor;
            $arMovimiento->Soporte = date('Ymd');
            $arMovimiento->IdCondEntrega = $request->condicion_entrega;
            $arMovimiento->Estado = 'DIGITADA';
            $arMovimiento->VrOtros = 0;
            $arMovimiento->IdUsuario = \Auth::user()->Usuario;
            $arMovimiento->IdAutoriza = \Auth::user()->Usuario;
            $arMovimiento->FhAutoriza = date("Y-m-d H:i:s");
            $arMovimiento->Prioridad = $request->prioridad;
            $arMovimiento->Comentarios = $request->comentarios;
            $arMovimiento->IdTpOc = 1;
            $arMovimiento->Total = $request->total;
            $arMovimiento->VrIva = $request->total_iva;
            $arMovimiento->SubTotal = $request->sub_total;
            $arMovimiento->save();

            $detalles = $request->data;

            foreach ( $detalles as  $ep=>$det ) {
                $MovimientoDet = new MovimientosDet();
                $MovimientoDet->IdMovimiento = $arMovimiento->IdMovimiento;
                $MovimientoDet->TpDocumento = $arMovimiento->TpDocumento;
                $MovimientoDet->IdDocumento = $arMovimiento->IdDocumento;
                $MovimientoDet->IdTercero = $arMovimiento->IdTercero;
                $MovimientoDet->Id_Item = $det['idarticulo'];
                $MovimientoDet->Cantidad = $det['cantidad'];
                $MovimientoDet->CantFactor = $MovimientoDet->Cantidad;
                $MovimientoDet->Operacion = $Doc->Operacion;
                $MovimientoDet->Factor = 1;
                $MovimientoDet->PorIva = $det['iva'];
                $MovimientoDet->TotalIva = ((($det['cantidad'] * $det['precio']) * $det['iva'])/100);
                $MovimientoDet->Precio = $det['precio'];
                $MovimientoDet->Estado ='DIGITADO';

                //iva
                $TotalIva = ((($MovimientoDet->Precio - $MovimientoDet->TotalDescuento) * $MovimientoDet->PorIva) / 100) * $MovimientoDet->Cantidad;

                //subtotal.
                $SubTotal = ($MovimientoDet->Precio * $MovimientoDet->Cantidad) - $MovimientoDet->TotalDescuento;

                //total
                $Total = $SubTotal + $TotalIva;

                $MovimientoDet->TotalIva = $TotalIva;
                $MovimientoDet->SubTotal = $SubTotal;
                $MovimientoDet->Total = $Total;

                $MovimientoDet->save();
            }
            //Autorizamos el documento
            $ValidaAut = \Funciones::AutorizarMovimiento($arMovimiento->IdMovimiento);
            \Funciones::Consecutivo($arMovimiento->IdMovimiento);
            
            //Notificaciones
            $FechaAct = date('Y-m-d');
            $NumPedidos = DB::table('movimientos')->Where('TpDocumento',20)->whereDate('Fecha',$FechaAct)->count();
            $NumFacturas = DB::table('movimientos')->Where('TpDocumento',5)->whereDate('Fecha',$FechaAct)->count();
            $ArregloDatos=[
                'pedidos'=>[
                    'Num'=>$NumPedidos,
                    'msj'=>'Pedidos'
                ],
                'facturas'=>[
                    'Num'=>$NumFacturas,
                    'msj'=>'Facturas'
                ]

            ];

            $usuarios = User::where('Inactivo','=','0')->get();
            //Creamos la notificacion en la tabla notificaciones
            foreach ($usuarios as $notificar) { 
                User::find($notificar->Usuario)->notify(new NotifyKasten($ArregloDatos)); 
            }

            DB::commit();
            //Enviamos el Email de alerta a el asesor
            $DatosCliente = \Funciones::ObtenerTercero($arMovimiento->IdTercero);
            $strMensaje = "El usuario  " . \Auth::user()->Nombres . " " . \Auth::user()->Apellidos . " de la institución " . $DatosCliente[0]->NombreCorto . " acaba de autorizar el pedido externo " . $arMovimiento->IdMovimiento;
            \Funciones::EnviarEmail('Autorización Pedido Externo','auxsistemas@aba.com.co',$strMensaje);
            return [
                'movimiento'=>$arMovimiento->IdMovimiento
            ];
        }
        catch(Exception $e){
            DB::rollBack();
            return[
                'error'=>$e->getMessage()
            ];
        } 
        
    }

    public function EliminarDetallePedido(Request $request){
        $MovDet = MovimientosDet::find($request->Id);
        $MovDet->delete();
        return[
            'deleted'=>true
        ];
    }


    public function update(Request $request){
        try{
            DB::beginTransaction();
            $arMovimiento = Movimientos::find($request->idmovimiento);
            $Tercero = Terceros::findOrFail($arMovimiento->IdTercero);
            $Doc = Documentos::find($arMovimiento->IdDocumento);
            $arMovimiento->Fecha1 = $request->fecha_minima;
            $arMovimiento->Fecha2 = $request->fecha_maxima;
            $arMovimiento->IdFormaPago = $Tercero->IdFormaPago;
            $arMovimiento->IdDireccion = $request->id_direccion;
            $arMovimiento->IdAsesor =  $Tercero->IdAsesor;
            $arMovimiento->Soporte = $request->num_orden;
            $arMovimiento->IdCondEntrega = $request->condicion_entrega;
            $arMovimiento->Estado = 'DIGITADA';
            $arMovimiento->VrOtros = 0;
            $arMovimiento->Prioridad = $request->prioridad;
            $arMovimiento->Comentarios = $request->comentarios;
            $arMovimiento->IdTpOc = 1;
            $arMovimiento->Total = $request->total;
            $arMovimiento->VrIva = $request->total_iva;
            $arMovimiento->SubTotal = $request->sub_total;
            $arMovimiento->save();

            $detalles = $request->data;

            foreach ( $detalles as  $ep=>$det ) {
                if($det['IdMovimientoDet'] > 0){
                    $MovimientoDet =  MovimientosDet::find($det['IdMovimientoDet']);
                }
                else{
                    $MovimientoDet = new MovimientosDet();
                }
                $MovimientoDet->IdMovimiento = $arMovimiento->IdMovimiento;
                $MovimientoDet->TpDocumento = $arMovimiento->TpDocumento;
                $MovimientoDet->IdDocumento = $arMovimiento->IdDocumento;
                $MovimientoDet->IdTercero = $arMovimiento->IdTercero;
                $MovimientoDet->Id_Item = $det['Id_Item'];
                $MovimientoDet->Cantidad = $det['Cantidad'];
                $MovimientoDet->CantFactor = $MovimientoDet->Cantidad;
                $MovimientoDet->Operacion = $Doc->Operacion;
                $MovimientoDet->Factor = 1;
                $MovimientoDet->PorIva = $det['PorIva'];
                $MovimientoDet->TotalIva = ((($det['Cantidad'] * $det['Precio']) * $det['PorIva'])/100);
                $MovimientoDet->Precio = $det['Precio'];
                $MovimientoDet->Estado ='DIGITADO';

                //iva
                $TotalIva = ((($MovimientoDet->Precio - $MovimientoDet->TotalDescuento) * $MovimientoDet->PorIva) / 100) * $MovimientoDet->Cantidad;

                //subtotal.
                $SubTotal = ($MovimientoDet->Precio * $MovimientoDet->Cantidad) - $MovimientoDet->TotalDescuento;

                //total
                $Total = $SubTotal + $TotalIva;

                $MovimientoDet->TotalIva = $TotalIva;
                $MovimientoDet->SubTotal = $SubTotal;
                $MovimientoDet->Total = $Total;

                $MovimientoDet->save();
            }
            //Autorizamos el documento
            $ValidaAut = \Funciones::AutorizarMovimiento($arMovimiento->IdMovimiento);
            DB::commit();
            return [ 
                'movimiento'=>$arMovimiento->IdMovimiento
            ];
        }
        catch(Exception $e){
            DB::rollBack();
            return[
                'error'=>$e->getMessage()
            ];
        } 
        
    }

    public function obtenerPedido(Request $request){

        $pedidos = Movimientos::select('IdMovimiento','NroDocumento','Fecha','Soporte',DB::raw('date_format(movimientos.Fecha1,"%Y-%m-%d") as Fecha1'),DB::raw('date_format(movimientos.Fecha2,"%Y-%m-%d") as Fecha2')
        ,'Total','Estado','movimientos.Comentarios','NmDireccion','IdFormaPago','IdCondEntrega','VrIva','asesores.Nombre as Asesor','movimientos.IdDireccion','movimientos.Soporte','movimientos.IdCondEntrega','movimientos.IdTercero','movimientos.IdAsesor','IdFormaPago')
        ->leftjoin('direcciones','movimientos.IdDireccion','=','direcciones.IdDireccion')
        ->leftjoin('asesores','asesores.IdAsesor','=','movimientos.IdAsesor')
        ->where('movimientos.IdMovimiento','=', $request->id)->limit(1)->get();

        $pedidosdet = MovimientosDet::select( 'IdMovimientoDet',  'movimientos_det.Id_Item', 'Cantidad', 'Precio','item.Descripcion','PorIva')
        ->leftjoin('item','item.Id_Item','=','movimientos_det.Id_Item')
        ->where('movimientos_det.IdMovimiento','=',$request->id)->get();
        return [
            'pedido' => $pedidos,
            'detalles' => $pedidosdet
        ];
    }

    public function ImprimirPedido(Request $request){
         $pedidos = Movimientos::select('IdMovimiento','NroDocumento','Fecha','Soporte',DB::raw('date_format(movimientos.Fecha1,"%Y-%m-%d") as Fecha1'),DB::raw('date_format(movimientos.Fecha2,"%Y-%m-%d") as Fecha2')
        ,'Total','Estado','movimientos.Comentarios','NmDireccion','IdFormaPago','IdCondEntrega','VrIva','asesores.Nombre as Asesor','movimientos.IdDireccion','movimientos.Soporte','movimientos.IdCondEntrega','movimientos.IdTercero','movimientos.IdAsesor','IdFormaPago')
        ->leftjoin('direcciones','movimientos.IdDireccion','=','direcciones.IdDireccion')
        ->leftjoin('asesores','asesores.IdAsesor','=','movimientos.IdAsesor')
        ->where('movimientos.IdMovimiento','=', $request->Id)->limit(1)->get();

        $pedidosdet = MovimientosDet::select( 'IdMovimientoDet',  'movimientos_det.Id_Item', 'Cantidad', 'Precio','item.Descripcion','PorIva')
        ->leftjoin('item','item.Id_Item','=','movimientos_det.Id_Item')
        ->where('movimientos_det.IdMovimiento','=',$request->Id)->get();
 
        $cont = count($pedidosdet);
        $pdf = \PDF::LoadView('pdf.imprimirpedido',['pedido'=>$pedidos[0],'detalles'=>$pedidosdet,'cont'=>$cont]);
        return $pdf->download('articulos.pdf');
    }
}
