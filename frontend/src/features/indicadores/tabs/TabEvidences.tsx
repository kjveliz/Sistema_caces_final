import { AlertCircle, BookOpen, ExternalLink, FileWarning, FolderOpen, Upload } from 'lucide-react';
import { toast } from 'sonner';

import type { Career, IndicatorDef } from '../../../types/index';
import { useEvidenciasIndicador } from '../hooks/useEvidenciasIndicador';
import CsvPreviewTable from '../components/CsvPreviewTable';
import XlsxPreviewTable from '../components/XlsxPreviewTable';

// ── Tab Evidencias (split view) ────────────────────────────────────────────
export default function TabEvidences({
  ind,
  career,
  cohort,
  idAsignatura,
  nombreAsignatura,
  onUpload,
  puedeCargar,
}: {
  ind: IndicatorDef;
  career: Career | null;
  cohort: string;
  idAsignatura: number | null;
  nombreAsignatura?: string | null;
  onUpload?: () => void;
  puedeCargar: boolean;
}) {
  const {
    mostrarAsignatura,
    slots,
    selected,
    setSelected,
    cargandoEvidencias,
    selectedSlot,
    urlDocumento,
    urlVistaPrevia,
    hasFile,
    esCsvInterno,
    esXlsxInterno,
    sinVistaPrevia,
    abrirDocumento,
  } = useEvidenciasIndicador({ ind, career, cohort, idAsignatura, nombreAsignatura });

  return (
    <div className="h-full flex px-6 py-4 max-w-5xl mx-auto gap-4 overflow-hidden">
      <div className="w-80 flex-shrink-0 flex flex-col overflow-hidden gap-2">
        {mostrarAsignatura && (
          <div
            className="bg-white rounded-xl px-3 py-2 flex items-center gap-2 flex-shrink-0"
            style={{
              border: '1px solid rgba(27,58,107,0.08)',
            }}
          >
            <BookOpen size={13} style={{ color: '#1B3A6B', flexShrink: 0 }} />
            <div className="min-w-0">
              <p
                className="text-[10px] font-bold uppercase tracking-wide"
                style={{ color: '#5A7295' }}
              >
                Asignatura
              </p>
              <p
                className="text-xs font-semibold truncate"
                style={{ color: '#0F1E3C' }}
                title={nombreAsignatura ?? undefined}
              >
                {nombreAsignatura}
              </p>
            </div>
          </div>
        )}

        <div
          className="bg-white rounded-2xl overflow-hidden flex-1 flex flex-col"
          style={{
            border: '1px solid rgba(27,58,107,0.08)',
          }}
        >
          <div
            className="px-4 py-3 flex-shrink-0 flex items-center justify-between"
            style={{
              borderBottom: '1px solid rgba(27,58,107,0.07)',
              background: '#F8FAFD',
            }}
          >
            <div>
              <h3
                className="text-xs font-bold"
                style={{
                  fontFamily: "'Libre Baskerville',serif",
                  color: '#0F1E3C',
                }}
              >
                Fuentes de información
              </h3>

              <p className="text-xs mt-0.5" style={{ color: '#5A7295' }}>
                {slots.filter((slot) => slot.file).length}/{slots.length} cargadas
              </p>
            </div>

            <span
              className="text-xs font-bold uppercase tracking-widest"
              style={{ color: '#5A7295' }}
            >
              Estado
            </span>
          </div>

          {cargandoEvidencias ? (
            <div className="flex-1 flex items-center justify-center px-5 text-center">
              <div>
                <div
                  className="w-7 h-7 mx-auto mb-3 rounded-full border-2 border-t-transparent animate-spin"
                  style={{
                    borderColor: '#1B3A6B transparent #1B3A6B #1B3A6B',
                  }}
                />

                <p className="text-xs font-semibold" style={{ color: '#5A7295' }}>
                  Consultando evidencias...
                </p>
              </div>
            </div>
          ) : (
            <div
              className="flex-1 overflow-auto divide-y"
              style={{
                borderColor: 'rgba(27,58,107,0.06)',
              }}
            >
              {slots.map((slot) => {
                const active = selected === slot.sourceNum;

                return (
                  <button
                    key={slot.sourceNum}
                    type="button"
                    onClick={() => setSelected(slot.sourceNum)}
                    className="w-full text-left px-4 py-3 flex items-center justify-between gap-3 transition-colors hover:bg-blue-50"
                    style={{
                      background: active ? '#EEF2F7' : 'transparent',
                      borderLeft: active ? '3px solid #1B3A6B' : '3px solid transparent',
                    }}
                  >
                    <p
                      className="text-xs font-semibold leading-snug flex-1"
                      style={{
                        color: active ? '#1B3A6B' : '#0F1E3C',
                      }}
                    >
                      {slot.label}
                    </p>

                    {slot.file ? (
                      <span
                        className="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0"
                        style={{
                          background: '#DCFCE7',
                          color: '#15803D',
                        }}
                      >
                        Cargado
                      </span>
                    ) : (
                      <span
                        className="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0"
                        style={{
                          background: '#FEF9C3',
                          color: '#92400E',
                        }}
                      >
                        Pendiente
                      </span>
                    )}
                  </button>
                );
              })}
            </div>
          )}
        </div>

        {puedeCargar && (
          <button
            type="button"
            onClick={() =>
              onUpload
                ? onUpload()
                : toast.info("Use el botón 'Cargar evidencias' desde el panel principal")
            }
            className="w-full flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-xs font-bold transition-all hover:opacity-90"
            style={{
              background: '#1B3A6B',
              color: '#fff',
            }}
          >
            <Upload size={12} />
            Cargar evidencias
          </button>
        )}
      </div>

      <div
        className="flex-1 flex flex-col overflow-hidden bg-white rounded-2xl"
        style={{
          border: '1px solid rgba(27,58,107,0.08)',
        }}
      >
        <div
          className="px-5 py-3 flex items-center justify-between flex-shrink-0"
          style={{
            borderBottom: '1px solid rgba(27,58,107,0.07)',
            background: '#F8FAFD',
          }}
        >
          <div className="min-w-0">
            <h3 className="text-xs font-bold" style={{ color: '#0F1E3C' }}>
              {selectedSlot ? selectedSlot.label : 'Vista previa'}
            </h3>

            {selectedSlot?.file && (
              <p className="text-xs mt-0.5 font-mono truncate" style={{ color: '#5A7295' }}>
                {selectedSlot.file.fileName}
              </p>
            )}
          </div>

          {hasFile && (
            <button
              type="button"
              onClick={abrirDocumento}
              className="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all hover:opacity-90"
              style={{
                background: '#1B3A6B',
                color: '#fff',
              }}
            >
              <ExternalLink size={11} />
              Abrir documento
            </button>
          )}
        </div>

        <div className="flex-1 overflow-hidden flex items-center justify-center min-h-0">
          {cargandoEvidencias ? (
            <div className="text-center px-8">
              <p className="text-sm font-medium" style={{ color: '#6B7280' }}>
                Cargando documentos...
              </p>
            </div>
          ) : hasFile && selectedSlot?.file && esCsvInterno ? (
            <CsvPreviewTable key={urlDocumento} url={urlDocumento} />
          ) : hasFile && selectedSlot?.file && esXlsxInterno ? (
            <XlsxPreviewTable key={urlDocumento} url={urlDocumento} />
          ) : hasFile && selectedSlot?.file && sinVistaPrevia ? (
            <div className="text-center px-8">
              <FileWarning size={40} className="mx-auto mb-3" style={{ color: '#D1D5DB' }} />

              <p className="text-sm font-medium" style={{ color: '#6B7280' }}>
                Vista previa no disponible para este tipo de archivo
              </p>

              <p className="text-xs mt-1" style={{ color: '#9CA3AF' }}>
                Use &ldquo;Abrir documento&rdquo; para descargarlo y verlo.
              </p>
            </div>
          ) : hasFile && selectedSlot?.file ? (
            <iframe
              key={urlVistaPrevia}
              src={urlVistaPrevia}
              className="w-full h-full"
              title={selectedSlot.file.fileName}
              style={{ border: 'none' }}
              allow="autoplay"
            />
          ) : selectedSlot ? (
            <div className="text-center px-8">
              <AlertCircle size={40} className="mx-auto mb-3" style={{ color: '#D1D5DB' }} />

              <p className="text-sm font-medium" style={{ color: '#6B7280' }}>
                Sin documento cargado
              </p>

              <p className="text-xs mt-1" style={{ color: '#9CA3AF' }}>
                Esta fuente aún no tiene archivo. Use “Cargar evidencias”.
              </p>
            </div>
          ) : (
            <div className="text-center px-8">
              <FolderOpen size={40} className="mx-auto mb-3" style={{ color: '#D1D5DB' }} />

              <p className="text-sm font-medium" style={{ color: '#6B7280' }}>
                Seleccione una fuente
              </p>

              <p className="text-xs mt-1" style={{ color: '#9CA3AF' }}>
                Haga clic en una fuente para visualizar el documento.
              </p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
