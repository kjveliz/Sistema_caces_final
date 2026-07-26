// Metadatos de presentación de cada EF (label/color) -- los VALORES salen
// de la API real (obtenerResultadoCohorte → detalle_asignaturas).
export const EF_META = [
  { id: 'EF1', key: 'ef1' as const, label: 'Seguimiento contenidos', color: '#2563EB' },
  { id: 'EF2', key: 'ef2' as const, label: 'Mejora micro currículo', color: '#16A34A' },
  { id: 'EF3', key: 'ef3' as const, label: 'Proceso difundido', color: '#0891B2' },
  { id: 'EF4', key: 'ef4' as const, label: 'Percepción estudiantil', color: '#D97706' },
  { id: 'EF5', key: 'ef5' as const, label: 'Marco normativo', color: '#7C3AED' },
];
