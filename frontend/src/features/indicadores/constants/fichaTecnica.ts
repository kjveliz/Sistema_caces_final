// ── EF criteria for I2 (Seguimiento de Syllabus) — 5 EFs, pesos ordinales ──
export const I2_EF_CRITERIA = [
  {
    id: 'EF1',
    weight: 0.33,
    label: 'Seguimiento de contenidos',
    desc: 'Se verifica que el docente haya cubierto los contenidos planificados en el sílabo durante el período académico, con respaldo documental de las sesiones ejecutadas.',
    checks: [
      'Registro de clases ejecutadas vs. planificadas en el sílabo',
      'Porcentaje de contenidos cubiertos por unidad temática',
      'Actas o informes de avance curricular firmados',
      'Evidencia de recuperación pedagógica si hubo desfase',
    ],
    rule: 'El docente acredita cobertura de los contenidos del sílabo con documentos de respaldo',
  },
  {
    id: 'EF2',
    weight: 0.27,
    label: 'Mejora del micro currículo',
    desc: 'Se evalúa si el docente realizó ajustes fundamentados al micro currículo (sílabo) con base en los resultados de aprendizaje obtenidos y las necesidades detectadas.',
    checks: [
      'Propuesta de mejora o actualización del sílabo presentada',
      'Justificación académica de los cambios realizados',
      'Aprobación por la coordinación académica competente',
      'Relación entre ajustes y resultados de aprendizaje previos',
    ],
    rule: 'Existe propuesta de mejora documentada y aprobada para el sílabo',
  },
  {
    id: 'EF3',
    weight: 0.2,
    label: 'Proceso difundido',
    desc: 'Se comprueba que el proceso de seguimiento del sílabo haya sido comunicado formalmente a los estudiantes y a las instancias académicas responsables.',
    checks: [
      'Acta o registro de socialización del sílabo con estudiantes',
      'Comunicación formal a la coordinación académica',
      'Evidencia de que los estudiantes conocen los criterios de evaluación',
    ],
    rule: 'El proceso de seguimiento fue comunicado y documentado formalmente',
  },
  {
    id: 'EF4',
    weight: 0.13,
    label: 'Difusión del sílabo en EVA',
    desc: 'Se constata que el sílabo de la asignatura se encuentre publicado y vigente en el Entorno Virtual de Aprendizaje (EVA) institucional durante el período evaluado.',
    checks: [
      'Sílabo publicado en la plataforma EVA institucional',
      'Fecha de publicación dentro del período académico',
      'El sílabo es la versión vigente y aprobada',
    ],
    rule: 'El sílabo está publicado en EVA durante el período evaluado',
  },
  {
    id: 'EF5',
    weight: 0.07,
    label: 'Normativa institucional',
    desc: 'Se verifica que el sílabo y su proceso de seguimiento cumplan con los lineamientos, formatos y disposiciones establecidas por la normativa interna del instituto.',
    checks: [
      'Sílabo elaborado en el formato oficial del instituto',
      'Cumplimiento de la estructura mínima exigida',
      'Aprobación del sílabo por la autoridad académica competente',
    ],
    rule: 'El sílabo y su seguimiento cumplen la normativa interna vigente',
  },
];

export const I2_EF_COLORS = ['#2563EB', '#16A34A', '#0891B2', '#CA8A04', '#7C3AED'];

// EF evaluation criteria shown in I3 Ficha Técnica
export const I3_EF_CRITERIA = [
  {
    id: 'EF1',
    weight: 0.4,
    label: 'Revisión documental',
    desc: 'El evaluador verifica que las evidencias presentadas estén completas, elaboradas en el formato oficial institucional y con fecha vigente dentro del período evaluado.',
    checks: [
      'Documentos completos y sin páginas faltantes',
      'Formato oficial del instituto (membrete, código)',
      'Fecha dentro del período académico evaluado',
      'Firma del docente y del responsable académico',
    ],
    rule: 'Presencia de al menos 1 documento válido y vigente',
  },
  {
    id: 'EF2',
    weight: 0.3,
    label: 'Coherencia interna',
    desc: 'Se comprueba que los datos consignados en las evidencias sean consistentes entre sí y con los registros del sistema académico institucional.',
    checks: [
      'Horas de tutoría declaradas coinciden con el sistema',
      'Número de estudiantes atendidos es consistente',
      'Fechas de las sesiones no se superponen ni contradicen',
      'Firmas y registros de asistencia concuerdan',
    ],
    rule: 'Los datos del documento coinciden con registros del sistema',
  },
  {
    id: 'EF3',
    weight: 0.2,
    label: 'Pertinencia',
    desc: 'Se valida que las evidencias presentadas correspondan efectivamente al período de evaluación definido por el modelo CACES y a la asignatura declarada.',
    checks: [
      'Período académico corresponde al evaluado (PAO correcto)',
      'La asignatura declarada coincide con la malla vigente',
      'El tipo de tutoría es pertinente al nivel de instrucción',
    ],
    rule: 'Evidencias pertenecen al período y asignatura correctos',
  },
  {
    id: 'EF4',
    weight: 0.1,
    label: 'Normativa institucional',
    desc: 'Se verifica que el proceso de tutorías se haya llevado a cabo conforme al reglamento interno y las disposiciones institucionales vigentes.',
    checks: [
      'Proceso cumple el reglamento interno de tutorías',
      'Número mínimo de horas reglamentarias cumplido',
      'Evidencia de socialización del plan de tutorías',
    ],
    rule: 'El proceso sigue la normativa institucional vigente',
  },
];

export const EF_COLORS = ['#0891B2', '#7C3AED', '#16A34A', '#EA580C'];
