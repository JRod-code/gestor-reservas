document.addEventListener("DOMContentLoaded", () => {
    const formFiltros = document.getElementById("formFiltros");
    const formNuevaReserva = document.getElementById("formNuevaReserva");

    if (formFiltros) {
        formFiltros.addEventListener("submit", (e) => {
            e.preventDefault();
            actualizarListado();
        });
    }

    if (formNuevaReserva) {
        formNuevaReserva.addEventListener("submit", (e) => {
            e.preventDefault();
            crearReserva();
        });
    }

    document.addEventListener("click", (e) => {
        if (e.target && e.target.classList.contains("btn-cancelar-reserva")) {
            const id = e.target.getAttribute("data-id");
            const contexto = e.target.getAttribute("data-contexto");
            cancelarReserva(id, contexto);
        }
    });
});

async function actualizarListado() {
    const loader = document.getElementById("loadingAlert");

    if (loader) loader.style.display = "block";

    const params = new URLSearchParams({
        api: 'reservas',
        busqueda: document.getElementById("filtroBusqueda").value || '',
        estado: document.getElementById("filtroEstado").value || '',
        fecha_desde: document.getElementById("filtroDesde").value || '',
        fecha_hasta: document.getElementById("filtroHasta").value || ''
    });

    try {
        const response = await fetch(`index.php?${params.toString()}`);
        if (!response.ok) throw new Error(`Estado: ${response.status}`);
        
        const data = await response.json();

        reconstruirTablaListado(data);
    } catch (err) {
        mostrarNotificacion("Error al recuperar los filtros desde la API.", "danger");
    } finally {
        if (loader) loader.style.display = "none";
    }
}

function reconstruirTablaListado(reservas) {
    const tbody = document.querySelector("#tablaReservas tbody");
    if (!tbody) return;
    
    tbody.innerHTML = "";

    if (reservas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted">Ningún registro coincide con los filtros aplicados.</td></tr>`;
        return;
    }

    reservas.forEach(r => {
        const importe = parseFloat(r.importe).toLocaleString('es-ES', { minimumFractionDigits: 2 });
        const btnCancelar = r.estado !== 'cancelada' 
            ? `<button type="button" class="btn btn-danger btn-small btn-cancelar-reserva" data-id="${r.id}" data-contexto="listado">Cancelar</button>` 
            : '';
            
        tbody.innerHTML += `
            <tr id="reserva-row-${r.id}">
                <td>
                    <strong>${sanearSalidaDOM(r.nombre_huesped)}</strong><br>
                    <small class="text-muted">${sanearSalidaDOM(r.email_huesped)}</small>
                </td>
                <td>${sanearSalidaDOM(r.nombre_alojamiento)}</td>
                <td>${formatearFecha(r.fecha_entrada)} al ${formatearFecha(r.fecha_salida)}</td>
                <td class="text-bold">${importe} €</td>
                <td class="status-cell">
                    <span class="badge badge-${r.estado}">${r.estado}</span>
                </td>
                <td>
                    <div class="actions-flex">
                        <a href="index.php?action=detalle&id=${r.id}" class="btn btn-secondary btn-small">Ver detalle</a>
                        ${btnCancelar}
                    </div>
                </td>
            </tr>
        `;
    });
}

async function crearReserva() {
    const errorSummary = document.getElementById("errorSummary");
    const entrada = document.getElementById("fecha_entrada")?.value;
    const salida = document.getElementById("fecha_salida")?.value;

    if (errorSummary) {
        errorSummary.style.display = "none";
        errorSummary.innerHTML = "";
    }

    if (new Date(entrada) >= new Date(salida)) {
        if (errorSummary) {
            errorSummary.style.display = "block";
            errorSummary.innerHTML = "La fecha de entrada debe ser anterior a la de salida.";
        }
        return;
    }

    const jsonPayload = {
        nombre_huesped: document.getElementById("nombre_huesped").value,
        email_huesped: document.getElementById("email_huesped").value,
        nombre_alojamiento: document.getElementById("nombre_alojamiento").value,
        importe: document.getElementById("importe").value,
        fecha_entrada: entrada,
        fecha_salida: salida,
        notas: document.getElementById("notas").value
    };

    try {
        const response = await fetch('index.php?api=reservas', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(jsonPayload)
        });

        const data = await response.json();
        if (response.status === 400 && data.errores) {
            if (errorSummary) {
                errorSummary.style.display = "block";
                data.errores.forEach(msg => {
                    errorSummary.innerHTML += `<p>Servidor: ${sanearSalidaDOM(msg)}</p>`;
                });
                return;
            }
        } else if (response.ok) {
            mostrarNotificacion("La reserva se ha insertado correctamente.", "success");
            document.getElementById("formNuevaReserva").reset();
            actualizarListado();
        } else {
            throw new Error();
        }
    } catch (e) {
        mostrarNotificacion("Error de procesamiento. El servidor retornó un fallo 500.", "danger");
    }
}

async function cancelarReserva(id, contexto) {
    if (!confirm(`¿Desea cancelar la reserva #${id}?`)) return;

    try {
        const response = await fetch(`index.php?api=reservas&id=${id}`, {
            method: 'POST', // Envío seguro unificado por POST para el Front Controller
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ estado: 'cancelada' })
        });
        if (response.ok) {
            mostrarNotificacion(`Estado de la reserva #${id} cambiado a cancelado.`, "success");
            if (contexto === "listado") {
                actualizarListado();
            } else {
                window.location.reload();
            }
        } else {
            mostrarNotificacion("No se pudo procesar la orden de cancelación.", "danger");
        }
    } catch (e) {
        mostrarNotificacion("Interrupción de red al contactar con la API.", "danger");
    }
}

function mostrarNotificacion(texto, clase) {
    const contenedor = document.getElementById("globalAlert");
    if (!contenedor) return;
    contenedor.innerHTML = `<div class="alert-${clase}">${texto}</div>`;
    setTimeout(() => contenedor.innerHTML = "", 5000);
}

function formatearFecha(fechaIso) {
    if (!fechaIso) return '';
    const trozos = fechaIso.split('-');
    if (trozos.length !== 3) return fechaIso;
    return `${trozos[2]}/${trozos[1]}/${trozos[0]}`;
}

function sanearSalidaDOM(contenidoCrudo) {
    if (!contenidoCrudo) return '';
    return contenidoCrudo.replace(/[&<>'"]/g, caracter => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    }[caracter] || caracter));
}
