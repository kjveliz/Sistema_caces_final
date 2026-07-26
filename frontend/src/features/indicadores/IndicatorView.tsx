import { useState } from 'react';

import {
  ArrowLeft,
  BarChart2,
  BookOpen,
  FolderOpen,
  TableProperties,
} from 'lucide-react';

import { calcRate, getStatus } from '../../shared/utils/evaluation';

import type { Career, IndicatorDef, TabId } from '../../types/index';

import TabResultsI2 from './tabs/TabResultsI2';
import TabResultsI3 from './tabs/TabResultsI3';
import TabCohorts from './tabs/TabCohorts';
import TabEvidences from './tabs/TabEvidences';
import TabFicha from './tabs/TabFicha';

export default function IndicatorView({
  indicator,
  onBack,
  career,
  cohort,
  pao,
  onUpload,
  puedeCargar,
}: {
  indicator: IndicatorDef;
  onBack: () => void;
  career: Career | null;
  cohort: string;
  pao: number;
  onUpload?: () => void;
  puedeCargar: boolean;
}) {
  const isTitDes = indicator.id === 'I4' || indicator.id === 'I5';
  const isI2 = indicator.id === 'I2';
  const isI3 = indicator.id === 'I3';
  const defaultTab: TabId = isI2 || isI3 ? 'results' : isTitDes ? 'cohorts' : 'evidences';
  const [tab, setTab] = useState<TabId>(defaultTab);
  const [idAsignaturaSeleccionada, setIdAsignaturaSeleccionada] = useState<number | null>(null);
  const [nombreAsignaturaSeleccionada, setNombreAsignaturaSeleccionada] = useState<string | null>(
    null,
  );
  const pct = calcRate(indicator.cohorts);
  const s = getStatus(pct);

  const TABS: { id: TabId; label: string; icon: React.ElementType }[] = [
    ...(isI2 ? [{ id: 'results' as TabId, label: 'Resultados', icon: BarChart2 }] : []),
    ...(isI3 ? [{ id: 'results' as TabId, label: 'Materias', icon: BarChart2 }] : []),
    ...(isTitDes ? [{ id: 'cohorts' as TabId, label: 'Cohortes', icon: TableProperties }] : []),
    { id: 'evidences', label: 'Evidencias', icon: FolderOpen },
    { id: 'ficha', label: 'Ficha técnica', icon: BookOpen },
  ];

  return (
    <div
      className="h-screen flex flex-col overflow-hidden"
      style={{ background: '#EEF2F7', fontFamily: "'Plus Jakarta Sans',sans-serif" }}
    >
      <div
        className="flex-shrink-0 border-b"
        style={{ background: '#fff', borderColor: 'rgba(27,58,107,0.1)' }}
      >
        <div className="max-w-6xl mx-auto px-6">
          <div className="h-12 flex items-center gap-3">
            <button
              onClick={onBack}
              className="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-colors"
              style={{ color: '#1B3A6B' }}
            >
              <ArrowLeft size={13} /> Docencia
            </button>
            <div className="h-4 w-px" style={{ background: 'rgba(27,58,107,0.15)' }} />
            <span
              className="text-xs font-bold tracking-widest uppercase"
              style={{ fontFamily: "'DM Mono',monospace", color: s.color }}
            >
              {indicator.code}
            </span>
            <span className="text-sm font-semibold truncate" style={{ color: '#0F1E3C' }}>
              {indicator.name}
            </span>
          </div>
          <div className="flex gap-0.5">
            {TABS.map((t) => {
              const Icon = t.icon;
              const active = tab === t.id;
              return (
                <button
                  key={t.id}
                  onClick={() => setTab(t.id)}
                  className="flex items-center gap-1.5 px-4 py-2.5 text-xs font-semibold rounded-t-lg border-b-2 transition-all"
                  style={{
                    color: active ? '#1B3A6B' : '#5A7295',
                    borderBottomColor: active ? '#1B3A6B' : 'transparent',
                    background: active ? 'rgba(27,58,107,0.05)' : 'transparent',
                  }}
                >
                  <Icon size={12} />
                  {t.label}
                </button>
              );
            })}
          </div>
        </div>
      </div>

      <div className="flex-1 overflow-hidden min-h-0">
        {tab === 'results' && isI2 && (
          <TabResultsI2
            ind={indicator}
            career={career}
            cohort={cohort}
            pao={pao}
            onAsignaturaChange={(id, nombre) => {
              setIdAsignaturaSeleccionada(id);
              setNombreAsignaturaSeleccionada(nombre ?? null);
            }}
          />
        )}
        {tab === 'results' && isI3 && (
          <TabResultsI3
            ind={indicator}
            career={career}
            cohort={cohort}
            pao={pao}
            onAsignaturaChange={(id, nombre) => {
              setIdAsignaturaSeleccionada(id);
              setNombreAsignaturaSeleccionada(nombre ?? null);
            }}
          />
        )}
        {tab === 'cohorts' && <TabCohorts ind={indicator} career={career} cohort={cohort} />}
        {tab === 'evidences' && (
          <TabEvidences
            ind={indicator}
            career={career}
            cohort={cohort}
            idAsignatura={idAsignaturaSeleccionada}
            nombreAsignatura={nombreAsignaturaSeleccionada}
            onUpload={onUpload}
            puedeCargar={puedeCargar}
          />
        )}
        {tab === 'ficha' && <TabFicha ind={indicator} />}
      </div>
    </div>
  );
}
