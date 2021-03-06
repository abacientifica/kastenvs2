<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MovimientosDet extends Model
{
    protected $table='movimientos_det';
    public $timestamps = false;
    protected $primaryKey ='IdMovimientoDet';
    protected $fillable = [
        'IdMovimiento',
        'FechaDet',
        'IdDocumento',
        'TpDocumento',
        'NroDocumento',
        'Enlace',
        'IdTercero',
        'Bodega',
        'BodDestino',
        'Id_Item',
        'Lote',
        'FhVencimiento',
        'Cantidad',
        'CantFactor',
        'CantAfectada',
        'Cantidad2',
        'UM',
        'Factor',
        'Costo',
        'CostoPromedio',
        'CostoNIIF',
        'IdLista',
        'Precio',
        'PorIva',
        'TotalIva',
        'TpDescuento',
        'CantDescuento',
        'TotalDescuento',
        'SubTotal',
        'Total',
        'Estado',
        'Operacion',
        'Comentarios',
        'CantOperada',
        'Cerrado',
        'CVenceAut',
        'IdAsesorComision',
        'PorComVenta',
        'ComisionVenta',
        'IdAsesorDet',
        'IdAsesorDetActual',
        'IdAsesorRecaudo',
        'PorComRecaudo',
        'Alistado',
        'CambioLote',
        'CostoEspecial',
        'AlistadoModificado',
        'CostoMvtoVig',
        'CostoVigEspecial',
        'ExentoIVA',
        'Confirmado',
        'Alternativa_CCto',
        'RegistroInvima',
        'FhVenceReg',
        'CertificadoInvima',
        'IdKit',
    ];
}
