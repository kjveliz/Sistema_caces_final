import { useState } from 'react';

import type { Career, EvidStep, IndicatorDef } from '../../types/index';

import { useCatalogoEvidencias } from './hooks/useCatalogoEvidencias';
import { useSubidaEvidencia } from './hooks/useSubidaEvidencia';

import StepSelectIndicator from './steps/StepSelectIndicator';
import StepConfigSyllabus from './steps/StepConfigSyllabus';
import StepConfigTitDes from './steps/StepConfigTitDes';
import StepUpload from './steps/StepUpload';

export default function EvidenceUploadView({
  career,
  indicators,
  onChange,
  onBack,
  preselectedCohort,
  preselectedIndicatorId,
}: {
  career: Career;
  indicators: IndicatorDef[];
  onChange: (inds: IndicatorDef[]) => void;
  onBack: () => void;
  preselectedCohort: string;
  preselectedIndicatorId?: string;
}) {
  const initialStep: EvidStep = preselectedIndicatorId
    ? ['I1', 'I2', 'I3'].includes(preselectedIndicatorId)
      ? 'configSyllabus'
      : 'configTitDes'
    : 'selectIndicator';
  const [step, setStep] = useState<EvidStep>(initialStep);

  const {
    pao,
    setPao,
    module,
    setModule,
    materia,
    setMateria,
    cohort,
    setCohort,
    asignaturaId,
    loadingCatalogo,
    catalogoError,
    mensajeSubida,
    setMensajeSubida,
    indicator,
    isSyllabus,
    handleSelectIndicator,
    updateSlot,
  } = useCatalogoEvidencias({
    career,
    indicators,
    onChange,
    preselectedCohort,
    preselectedIndicatorId,
    setStep,
  });

  const { guardando, subiendoEvidencia, procesarPdf, guardarEvidenciasSeleccionadas } =
    useSubidaEvidencia({
      career,
      cohort,
      indicator,
      asignaturaId,
      updateSlot,
      onBack,
      setMensajeSubida,
    });

  // Step 1: Select indicator
  if (step === 'selectIndicator') {
    return (
      <StepSelectIndicator
        career={career}
        indicators={indicators}
        onBack={onBack}
        onSelectIndicator={handleSelectIndicator}
      />
    );
  }

  // Step 2a: Config for Syllabus indicators (I1/I2/I3)
  if (step === 'configSyllabus') {
    return (
      <StepConfigSyllabus
        career={career}
        indicator={indicator}
        preselectedIndicatorId={preselectedIndicatorId}
        onBack={onBack}
        onBackToSelectIndicator={() => setStep('selectIndicator')}
        cohort={cohort}
        setCohort={setCohort}
        pao={pao}
        setPao={setPao}
        module={module}
        setModule={setModule}
        materia={materia}
        setMateria={setMateria}
        onContinue={() => setStep('upload')}
      />
    );
  }

  // Step 2b: Config for Titulación/Deserción (I4/I5)
  if (step === 'configTitDes') {
    return (
      <StepConfigTitDes
        indicator={indicator}
        preselectedIndicatorId={preselectedIndicatorId}
        onBack={onBack}
        onBackToSelectIndicator={() => setStep('selectIndicator')}
        cohort={cohort}
        setCohort={setCohort}
        onContinue={() => setStep('upload')}
      />
    );
  }

  // Step 3: Upload files
  if (step === 'upload' && indicator) {
    return (
      <StepUpload
        career={career}
        indicator={indicator}
        isSyllabus={isSyllabus}
        pao={pao}
        module={module}
        materia={materia}
        cohort={cohort}
        loadingCatalogo={loadingCatalogo}
        catalogoError={catalogoError}
        guardando={guardando}
        subiendoEvidencia={subiendoEvidencia}
        mensajeSubida={mensajeSubida}
        onBackToConfig={() => setStep(isSyllabus ? 'configSyllabus' : 'configTitDes')}
        updateSlot={updateSlot}
        procesarPdf={procesarPdf}
        guardarEvidenciasSeleccionadas={guardarEvidenciasSeleccionadas}
      />
    );
  }

  return null;
}
