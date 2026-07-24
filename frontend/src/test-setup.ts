// Setup global de Vitest (ver vitest.config.ts -> test.setupFiles).
//
// Registra los matchers de @testing-library/jest-dom (toBeInTheDocument,
// toBeDisabled, toHaveTextContent, etc.) para que estén disponibles en
// cualquier test .test.tsx sin tener que importarlos archivo por archivo.
import '@testing-library/jest-dom/vitest';

import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';

// A diferencia de Jest, Vitest no desmonta automáticamente lo que quedó
// renderizado por un test anterior (RTL registra su cleanup automático
// apoyándose en el afterEach global de Jest, que Vitest no expone salvo que
// se pida explícitamente). Sin esto, los renders de un test quedan montados
// en el DOM y contaminan las queries (screen.getByRole, etc.) del siguiente
// test del mismo archivo.
afterEach(() => {
  cleanup();
});
