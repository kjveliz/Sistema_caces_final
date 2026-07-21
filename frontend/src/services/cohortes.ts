export interface CohorteEvaluacion {
  id_cohorte: number;
  nombre_cohorte: string;
  fecha_inicio: string | null;
  fecha_fin: string | null;
  id_carrera: number;
  carrera: string;
  codigo_carrera: string;
  id_evaluacion: number | null;
  nombre_evaluacion: string | null;
  estado: string | null;
}

interface ListarResponse {
  ok: boolean;
  mensaje?: string;
  datos?: CohorteEvaluacion[];
}

export interface CrearCohorteParams {
  idCarrera: number;
  nombreCohorte: string;
  fechaInicio: string;
  fechaFin: string;
  estado: "Activa" | "Pendiente" | "Cerrada";
}

interface CrearResponse {
  ok: boolean;
  mensaje: string;
  datos?: CohorteEvaluacion;
}

export async function listarCohortesEvaluaciones(): Promise<CohorteEvaluacion[]> {
  const respuesta = await fetch(
    "http://localhost/sistemacaces/api/administracion/cohortes/listar.php",
    {
      method: "GET",
      credentials: "include",
      headers: {
        Accept: "application/json",
      },
    },
  );

  const datos = (await respuesta.json()) as ListarResponse;

  if (!respuesta.ok || !datos.ok || !datos.datos) {
    throw new Error(
      datos.mensaje ||
        "No se pudieron consultar las cohortes.",
    );
  }

  return datos.datos;
}

export async function crearCohorteEvaluacion(
  parametros: CrearCohorteParams,
): Promise<CohorteEvaluacion> {
  const respuesta = await fetch(
    "http://localhost/sistemacaces/api/administracion/cohortes/crear.php",
    {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        id_carrera: parametros.idCarrera,
        nombre_cohorte: parametros.nombreCohorte,
        fecha_inicio: parametros.fechaInicio,
        fecha_fin: parametros.fechaFin,
        estado: parametros.estado,
      }),
    },
  );

  const datos = (await respuesta.json()) as CrearResponse;

  if (!respuesta.ok || !datos.ok || !datos.datos) {
    throw new Error(
      datos.mensaje ||
        "No se pudo crear la cohorte.",
    );
  }

  return datos.datos;
}

export async function cambiarEstadoEvaluacion(
  idEvaluacion: number,
  estado: "Activa" | "Pendiente" | "Cerrada",
): Promise<void> {
  const respuesta = await fetch(
    "http://localhost/sistemacaces/api/administracion/cohortes/cambiar_estado.php",
    {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        id_evaluacion: idEvaluacion,
        estado,
      }),
    },
  );

  const datos = await respuesta.json();

  if (!respuesta.ok || !datos.ok) {
    throw new Error(
      datos.mensaje ||
        "No se pudo actualizar la evaluación.",
    );
  }
}
