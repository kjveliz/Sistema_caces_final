import { useRef } from "react";
import {
  CheckCircle2,
  XCircle,
  FileText,
  Upload,
  Loader2,
} from "lucide-react";

import type { EvidenceSlot } from "../../types";
import { validatePDF, validateCSV } from "../../utils/pdf";

interface PdfZoneProps {
  slot: EvidenceSlot;
  fileName: string;
  onChange: (slot: EvidenceSlot) => void;
  onFileSelected?: (
    slot: EvidenceSlot,
    file: File,
  ) => void;
  disabled?: boolean;
  loading?: boolean;
}

export default function PdfZone({
  slot,
  fileName,
  onChange,
  onFileSelected,
  disabled = false,
  loading = false,
}: PdfZoneProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const esCsv = slot.acceptedType === "csv";
  const NOMBRES_INDICADORES: Record<string, string> = {
  I1: "Syllabus",
  I2: "Seguimiento del syllabus",
  I3: "Tutorías académicas",
  I4: "Tasa de Deserción",
  I5: "Tasa de Titulación",
};

  function pick(
    event: React.ChangeEvent<HTMLInputElement>,
  ) {
    if (loading) {
      event.target.value = "";
      return;
    }

    const file = event.target.files?.[0];

    if (!file) {
      return;
    }

    const error = esCsv ? validateCSV(file) : validatePDF(file);

    if (error) {
      onChange({
        ...slot,
        error,
        file: undefined,
      });

      event.target.value = "";
      return;
    }

    /*
      Si el componente padre proporciona onFileSelected,
      le entregamos el archivo para que sea validado por PHP
      y se genere el nombre oficial.
    */
    if (onFileSelected) {
      onFileSelected(slot, file);
      event.target.value = "";
      return;
    }

    /*
      Este bloque funciona como respaldo.
      Se usa únicamente si no se proporcionó onFileSelected.
    */
    onChange({
      ...slot,
      error: undefined,
      file: {
        fileName,
        originalName: file.name,
        url: URL.createObjectURL(file),
        size: file.size,
        rawFile: file,
      },
    });

    event.target.value = "";
  }

  const hasFile = Boolean(slot.file);
  const hasError = Boolean(slot.error);
  const isShared = disabled && hasFile;

  return (
    <div
      className="rounded-xl p-3 border transition-all"
      style={{
        borderColor: hasFile
          ? "#16A34A"
          : hasError
            ? "#DC2626"
            : "rgba(27,58,107,0.15)",
        background: hasFile
          ? "#F0FDF4"
          : hasError
            ? "#FEF2F2"
            : "#F8FAFD",
        opacity: (disabled && !hasFile) || loading ? 0.65 : 1,
      }}
    >
      <div className="flex items-center gap-2.5">
        <div className="flex-shrink-0">
          {hasFile ? (
            <CheckCircle2
              size={15}
              style={{ color: "#16A34A" }}
            />
          ) : hasError ? (
            <XCircle
              size={15}
              style={{ color: "#DC2626" }}
            />
          ) : (
            <FileText
              size={15}
              style={{ color: "#5A7295" }}
            />
          )}
        </div>

        <div className="flex-1 min-w-0">
          <p
            className="text-xs font-semibold leading-snug"
            style={{ color: "#0F1E3C" }}
          >
            {slot.label}
          </p>

          {hasFile ? (
            <>
              <p
                className="text-xs font-bold mt-0.5 break-all"
                style={{
                  color: "#16A34A",
                  fontFamily: "'DM Mono',monospace",
                }}
              >
                {slot.file?.fileName}
              </p>

              {isShared && (
                <div className="mt-1">
                  <p
                    className="text-xs font-semibold"
                    style={{ color: "#2563EB" }}
                  >
                    🔗 Evidencia compartida
                  </p>

                  <p
                    className="text-xs"
                    style={{ color: "#5A7295" }}
                  >
                    Indicador: {slot.sharedFrom
                      ? NOMBRES_INDICADORES[slot.sharedFrom] ?? slot.sharedFrom
                      : "Otro indicador"}
                  </p>
                </div>
              )}
            </>
          ) : hasError ? (
            <p
              className="text-xs mt-0.5"
              style={{ color: "#DC2626" }}
            >
              {slot.error}
            </p>
          ) : (
            <p
              className="text-xs mt-0.5 break-all"
              style={{
                color: "#9CA3AF",
                fontFamily: "'DM Mono',monospace",
              }}
            >
              {fileName}
            </p>
          )}
        </div>

        {!disabled && (
          <button
            type="button"
            onClick={() => !loading && inputRef.current?.click()}
            disabled={loading}
            className="flex-shrink-0 flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all hover:opacity-80 disabled:cursor-not-allowed disabled:opacity-70"
            style={{
              background: hasFile
                ? "#16A34A"
                : "#1B3A6B",
              color: "#fff",
            }}
          >
            {loading ? (
              <Loader2 size={10} className="animate-spin" />
            ) : (
              <Upload size={10} />
            )}

            {loading
              ? "Cargando..."
              : hasFile
                ? "Cambiar"
                : "Subir"}
          </button>
        )}
      </div>

      <input
        ref={inputRef}
        type="file"
        accept={
          esCsv
            ? ".csv,text/csv"
            : ".pdf,application/pdf"
        }
        className="hidden"
        onChange={pick}
        disabled={loading || disabled}
      />
    </div>
  );
}