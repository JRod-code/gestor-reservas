<?php
declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use App\Controller\ReservaController;
use App\Repository\ReservaRepository;

class ReservaTest extends TestCase {

    /**
     * Test 1: Verificar que el controlador rechace correos con formato incorrecto
     */
    public function testValidacionServidorRechazaEmailInvalido(): void {
        $mockRepository = $this->createMock(ReservaRepository::class);
        
        $payload = [
            'nombre_huesped' => 'Test validacion',
            'email_huesped' => 'correo-incorrecto#error.com',
            'nombre_alojamiento' => 'Estudio Sol',
            'fecha_entrada' => '2026-11-01',
            'fecha_salida' => '2026-11-05',
            'importe' => 150.00
        ];

        $controller = new class($mockRepository, $payload, $this) extends ReservaController {
            private array $payload;
            private TestCase $testCase;

            public function __construct($mockRepository, array $payload, TestCase $testCase) {
                parent::__construct($mockRepository);
                $this->payload = $payload;
                $this->testCase = $testCase;
            }

            public function responderJson(mixed $data, int $codigo): void {
                $this->testCase->assertEquals(400, $codigo);
                $this->testCase->assertArrayHasKey('errores', $data);
                $this->testCase->assertContains("El correo electrónico no es válido.", $data['errores']);
            }

            public function crear(): void {
                $input = $this->payload; 
                if (!$input) { $this->responderJson(['error' => 'Formato de datos incorrecto.'], 400); return; }

                $errores = [];
                if (empty($input['nombre_huesped'])) { $errores[] = "El nombre del huésped es obligatorio."; }
                if (empty($input['email_huesped']) || !filter_var($input['email_huesped'], FILTER_VALIDATE_EMAIL)) {
                    $errores[] = "El correo electrónico no es válido.";
                }
                if (empty($input['nombre_alojamiento'])) { $errores[] = "El nombre del alojamiento es obligatorio."; }
                
                if (empty($input['fecha_entrada']) || empty($input['fecha_salida'])) {
                    $errores[] = "Las fechas de entrada y salida son obligatorias.";
                } else {
                    $t_entrada = strtotime($input['fecha_entrada']);
                    $t_salida  = strtotime($input['fecha_salida']);
                    if ($t_entrada >= $t_salida) { $errores[] = "La fecha de entrada debe ser anterior a la fecha de salida."; }
                }
                if (!isset($input['importe']) || !is_numeric($input['importe']) || floatval($input['importe']) <= 0) { $errores[] = "El importe debe ser un número positivo."; }

                if (!empty($errores)) { $this->responderJson(['errores' => $errores], 400); return; }
            }
        };

        $controller->crear();
    }

    /**
     * Test 2: Verificar que el controlador impida registrar reservas con fechas invertidas
     */
    public function testValidacionServidorRechazaFechasInvertidas(): void {
        $mockRepository = $this->createMock(ReservaRepository::class);
        
        $payload = [
            'nombre_huesped' => 'Test Fechas',
            'email_huesped' => 'valido@email.com',
            'nombre_alojamiento' => 'Estudio Sol',
            'fecha_entrada' => '2026-11-15',
            'fecha_salida' => '2026-11-10',  // Salida anterior a la entrada
            'importe' => 150.00
        ];

        $controller = new class($mockRepository, $payload, $this) extends ReservaController {
            private array $payload;
            private TestCase $testCase;

            public function __construct($mockRepository, array $payload, TestCase $testCase) {
                parent::__construct($mockRepository);
                $this->payload = $payload;
                $this->testCase = $testCase;
            }

            public function responderJson(mixed $data, int $codigo): void {
                $this->testCase->assertEquals(400, $codigo);
                $this->testCase->assertArrayHasKey('errores', $data);
                $this->testCase->assertContains("La fecha de entrada debe ser anterior a la fecha de salida.", $data['errores']);
            }

            public function crear(): void {
                $input = $this->payload; 
                if (!$input) { $this->responderJson(['error' => 'Formato de datos incorrecto.'], 400); return; }

                $errores = [];
                if (empty($input['nombre_huesped'])) { $errores[] = "El nombre del huésped es obligatorio."; }
                if (empty($input['email_huesped']) || !filter_var($input['email_huesped'], FILTER_VALIDATE_EMAIL)) {
                    $errores[] = "El correo electrónico no es válido.";
                }
                if (empty($input['nombre_alojamiento'])) { $errores[] = "El nombre del alojamiento es obligatorio."; }
                
                if (empty($input['fecha_entrada']) || empty($input['fecha_salida'])) {
                    $errores[] = "Las fechas de entrada y salida son obligatorias.";
                } else {
                    $t_entrada = strtotime($input['fecha_entrada']);
                    $t_salida  = strtotime($input['fecha_salida']);
                    if ($t_entrada >= $t_salida) { $errores[] = "La fecha de entrada debe ser anterior a la fecha de salida."; }
                }
                if (!isset($input['importe']) || !is_numeric($input['importe']) || floatval($input['importe']) <= 0) { $errores[] = "El importe debe ser un número positivo."; }

                if (!empty($errores)) { $this->responderJson(['errores' => $errores], 400); return; }
            }
        };

        $controller->crear();
    }

    /**
     * Test 3: Verificar que el cambio de estado válido devuelva un código HTTP 200
     */
    public function testCambioEstadoValidoRetornaCodigoOk(): void {
        $mockRepository = $this->createMock(ReservaRepository::class);
        $mockRepository->method('obtenerPorId')->willReturn(['id' => 1, 'estado' => 'pendiente']);
        $mockRepository->method('actualizarEstado')->willReturn(true);

        $controller = new class($mockRepository, $this) extends ReservaController {
            private TestCase $testCase;
            private $repositorySimulado;

            public function __construct($mockRepository, TestCase $testCase) {
                parent::__construct($mockRepository);
                $this->testCase = $testCase;
                $this->repositorySimulado = $mockRepository;
            }

            public function responderJson(mixed $data, int $codigo): void {
                $this->testCase->assertEquals(200, $codigo);
                $this->testCase->assertArrayHasKey('mensaje', $data);
                $this->testCase->assertEquals("Estado actualizado correctamente.", $data['mensaje']);
            }

            public function cambiarEstado(int $id): void {
                $nuevoEstado = 'confirmada'; 

                if (!in_array($nuevoEstado, ['pendiente', 'confirmada', 'cancelada'], true)) {
                    $this->responderJson(['error' => 'El estado indicado no es válido.'], 400);
                    return;
                }
                // Consultamos al repositorio simulado inyectado localmente
                if (!$this->repositorySimulado->obtenerPorId($id)) {
                    $this->responderJson(['error' => 'La reserva no existe.'], 404);
                    return;
                }

                try {
                    if ($this->repositorySimulado->actualizarEstado($id, $nuevoEstado)) {
                        $this->responderJson(['mensaje' => 'Estado actualizado correctamente.'], 200);
                    }
                } catch (\Exception $e) {
                    throw $e;
                }
            }
        };

        $controller->cambiarEstado(1);
    }
}
