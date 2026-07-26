import { ArrowLeft, FileText, Loader2 } from 'lucide-react';

import PdfZone from '../../../shared/components/PdfZone';
import Breadcrumb from '../../../shared/components/Breadcrumb';

import type { Career, EvidenceSlot, IndicatorDef } from '../../../types/index';

export default function StepUpload({
  career,
  indicator,
  isSyllabus,
  pao,
  module,
  materia,
  cohort,
  loadingCatalogo,
  catalogoError,
  guardando,
  subiendoEvidencia,
  mensajeSubida,
  onBackToConfig,
  updateSlot,
  procesarPdf,
  guardarEvidenciasSeleccionadas,
}: {
  career: Career;
  indicator: IndicatorDef;
  isSyllabus: boolean;
  pao: string;
  module: string;
  materia: string;
  cohort: string;
  loadingCatalogo: boolean;
  catalogoError: string;
  guardando: boolean;
  subiendoEvidencia: boolean;
  mensajeSubida: string;
  onBackToConfig: () => void;
  updateSlot: (indId: string, updated: EvidenceSlot) => void;
  procesarPdf: (slot: EvidenceSlot, archivo: File) => void;
  guardarEvidenciasSeleccionadas: () => void;
}) {
  const contextLabel = isSyllabus
    ? `${pao} · Módulo ${module} · ${materia}`
    : `Cohorte ${cohort}`;
  const done = indicator.slots.filter((s) => s.file).length;
  const total = indicator.slots.length;

  return (
    <div
      className="h-screen flex flex-col overflow-hidden"
      style={{ background: '#EEF2F7', fontFamily: "'Plus Jakarta Sans',sans-serif" }}
    >
      <div
        className="flex-shrink-0 border-b"
        style={{ background: '#fff', borderColor: 'rgba(27,58,107,0.1)' }}
      >
        <div className="max-w-3xl mx-auto px-6 h-14 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <button
              onClick={onBackToConfig}
              className="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-colors"
              style={{ color: '#1B3A6B' }}
            >
              <ArrowLeft size={13} /> Configuración
            </button>
            <div className="h-4 w-px" style={{ background: 'rgba(27,58,107,0.15)' }} />
            <span
              className="text-sm font-bold"
              style={{ fontFamily: "'Libre Baskerville',serif", color: '#0F1E3C' }}
            >
              {indicator.name}
            </span>
          </div>
          <span className="text-xs font-mono" style={{ color: done === total ? '#16A34A' : '#5A7295' }}>
            {done}/{total}
          </span>
        </div>
      </div>
      <div className="flex-1 overflow-auto">
        <div className="max-w-3xl mx-auto px-6 py-6">
          <Breadcrumb
            items={['Seleccionar indicador', indicator.name, 'Configuración', 'Cargar archivos']}
          />

          {/* Context info */}
          <div
            className="rounded-xl px-4 py-3 mb-5 flex items-center gap-3"
            style={{ background: '#EEF2F7', border: '1px solid rgba(27,58,107,0.12)' }}
          >
            <div
              className="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
              style={{ background: '#1B3A6B' }}
            >
              <FileText size={14} className="text-white" />
            </div>
            <div>
              <p className="text-xs font-bold" style={{ color: '#0F1E3C' }}>
                {indicator.code} · {indicator.name}
              </p>
              <p className="text-xs" style={{ color: '#5A7295' }}>
                {contextLabel}
              </p>
            </div>
          </div>

          <h3 className="text-sm font-bold mb-3" style={{ color: '#0F1E3C' }}>
            Fuentes de información requeridas
          </h3>
          {loadingCatalogo && (
            <div
              className="rounded-xl px-4 py-3 mb-3 text-xs"
              style={{
                background: '#EEF5FF',
                color: '#1B3A6B',
                border: '1px solid rgba(37,99,235,0.15)',
              }}
            >
              Cargando fuentes de información desde la base de datos...
            </div>
          )}

          {catalogoError && (
            <div
              className="rounded-xl px-4 py-3 mb-3 text-xs"
              style={{
                background: '#FEE2E2',
                color: '#DC2626',
                border: '1px solid rgba(220,38,38,0.15)',
              }}
            >
              {catalogoError}
            </div>
          )}
          <div
            className={`grid gap-3 ${indicator.slots.length <= 2 ? 'grid-cols-2' : indicator.slots.length === 3 ? 'grid-cols-3' : 'grid-cols-2'}`}
          >
            {indicator.slots.map((slot) => (
              <PdfZone
                key={slot.sourceNum}
                slot={slot}
                fileName={`${career.code}.${cohort.replace(/\s+/g, '')}.C${career.criterionNum}.${indicator.num}.${slot.sourceNum}.${slot.nombreArchivoBase ?? 'Evidencia'}.${slot.acceptedType === 'csv' ? 'csv' : 'pdf'}`}
                onChange={(updated) => updateSlot(indicator.id, updated)}
                onFileSelected={(selectedSlot, archivo) => procesarPdf(selectedSlot, archivo)}
                disabled={!!slot.sharedFrom || subiendoEvidencia}
                loading={subiendoEvidencia}
              />
            ))}
          </div>

          {/* Guardar y volver at bottom */}
          <div className="flex justify-end mt-5">
            <button
              type="button"
              onClick={guardarEvidenciasSeleccionadas}
              disabled={guardando || subiendoEvidencia}
              className="px-6 py-2.5 rounded-xl text-xs font-bold transition-all hover:opacity-90 active:scale-95"
              style={{
                background: guardando || subiendoEvidencia ? '#94A3B8' : '#1B3A6B',
                color: '#fff',
                cursor: guardando || subiendoEvidencia ? 'not-allowed' : 'pointer',
              }}
            >
              {guardando ? 'Guardando...' : 'Guardar y volver →'}
            </button>
          </div>
        </div>
      </div>

      {subiendoEvidencia && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center px-4"
          style={{
            background: 'rgba(15,30,60,0.48)',
            backdropFilter: 'blur(2px)',
          }}
          role="dialog"
          aria-modal="true"
          aria-live="polite"
        >
          <div
            className="w-full max-w-sm rounded-2xl bg-white px-6 py-6 text-center"
            style={{
              border: '1px solid rgba(27,58,107,0.12)',
              boxShadow: '0 20px 60px rgba(15,30,60,0.25)',
            }}
          >
            <div
              className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full"
              style={{ background: '#EEF5FF', color: '#1B3A6B' }}
            >
              <Loader2 size={24} className="animate-spin" />
            </div>

            <h3 className="text-base font-bold" style={{ color: '#0F1E3C' }}>
              Cargando evidencia...
            </h3>

            <p className="mt-2 text-sm" style={{ color: '#5A7295' }}>
              {mensajeSubida}
            </p>

            <p className="mt-3 text-xs" style={{ color: '#94A3B8' }}>
              Por favor, no cierre esta ventana hasta que finalice el proceso.
            </p>
          </div>
        </div>
      )}
    </div>
  );
}
