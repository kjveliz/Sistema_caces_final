export type TabId = 'cohorts' | 'evidences' | 'ficha' | 'results';

export type EvidStep = 'selectIndicator' | 'configSyllabus' | 'configTitDes' | 'upload';

export interface UploadedFile {
  fileName: string;
  originalName: string;
  url: string;
  size: number;
  rawFile?: File;
  serverUrl?: string;
  // Solo presente cuando el archivo viene de `evidencia_asignatura` (I2/I3).
  // Permite pedir el contenido vía GET /evidencia-asignatura/ver en vez de
  // abrir `url_archivo` directo — necesario porque esa URL puede ser una
  // ruta de filesystem local (no abrible desde el navegador) si la carrera
  // está en modo 'local' — ver plan_interruptor_almacenamiento.txt §4.5/§4.6
  // y MEMORIA v91 §68.1.
  idEvidenciaAsig?: number;
}

export interface EvidenceSlot {
  sourceNum: number;
  label: string;

  // Tipo de archivo que acepta este slot. Si no se especifica, se asume
  // "pdf" (comportamiento histórico de todos los slots existentes).
  acceptedType?: 'pdf' | 'csv';

  idCatalogo?: number;
  codigoEvidencia?: string;
  nombreArchivoBase?: string;
  descripcionCompleta?: string;
  idEvidencia?: number;

  file?: UploadedFile;
  error?: string;
  sharedKey?: string;
  sharedFrom?: string;
}
export interface CohortRow {
  period: string;
  enrolled: number;
  graduated: number;
}

export interface IndicatorDef {
  id: string;
  num: number;
  code: string;
  name: string;
  description: string;
  formula: string;
  period: string;
  purpose: string;
  slots: EvidenceSlot[];
  cohorts: CohortRow[];
}

export interface Career {
  name: string;
  code: string;
  criterionNum: number;
  clickable: boolean;
}

export interface CareerArea {
  name: string;
  image: string;
  careers: Career[];
}
