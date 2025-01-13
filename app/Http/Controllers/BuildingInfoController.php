<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class BuildingInfoController extends Controller 
{
    private $searchUrl = 'https://api.bdnb.io/v1/bdnb/donnees/batiment_groupe_complet';
    private $tilesUrl = 'https://api.bdnb.io/v1/bdnb/tuiles/batiment_groupe';

    /**
     * Recherche de bâtiments avec filtres
     */
    public function searchBuildings(Request $request)
    {
        try {
            // Validation des paramètres de recherche
            $request->validate([
                'building_id' => 'nullable|string',
                'min_height' => 'nullable|numeric',
                'max_height' => 'nullable|numeric',
                'min_age' => 'nullable|integer',
                'max_age' => 'nullable|integer'
            ]);

            // Construction des conditions de recherche
            $conditions = [];

            // Filtre par ID du bâtiment
            if ($request->has('building_id')) {
                $conditions[] = "batiment_groupe_id=eq.{$request->building_id}";
            }

            // Filtre par hauteur
            if ($request->has('min_height')) {
                $conditions[] = "hauteur_mean=eq.{$request->min_height}";
            }
            if ($request->has('max_height')) {
                $conditions[] = "hauteur_mean=eq.{$request->max_height}";
            }

            // Filtre par âge
            $currentYear = date('Y');
            if ($request->has('min_age')) {
                $maxYear = $currentYear - $request->min_age;
                $conditions[] = "annee_construction=eq.{$maxYear}";
            }
            if ($request->has('max_age')) {
                $minYear = $currentYear - $request->max_age;
                $conditions[] = "annee_construction=eq.{$minYear}";
            }

            // Construction de l'URL
            $url = $this->searchUrl;
            if (!empty($conditions)) {
                $url .= '?' . implode('&', $conditions);
            }

            Log::info("Requête de recherche BDNB", [
                'url' => $url,
                'conditions' => $conditions
            ]);

            $client = new Client([
                'verify' => false,
                'timeout' => 30
            ]);

            $response = $client->get($url);

            return response($response->getBody())
                ->header('Content-Type', 'application/json')
                ->header('Access-Control-Allow-Origin', '*');

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error("Erreur client lors de la recherche", [
                'status' => $e->getResponse()->getStatusCode(),
                'body' => $e->getResponse()->getBody()->getContents(),
                'url' => $url ?? null
            ]);

            return response()->json([
                'error' => 'Erreur lors de la recherche',
                'message' => $e->getMessage()
            ], $e->getResponse()->getStatusCode());

        } catch (\Exception $e) {
            Log::error("Erreur lors de la recherche", [
                'message' => $e->getMessage(),
                'url' => $url ?? null
            ]);

            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupération des tuiles vectorielles
     */
    public function getTile($z, $x, $y)
    {
        if (!$this->isValidTileCoordinate($z, $x, $y)) {
            return response()->json([
                'error' => 'Coordonnées de tuile invalides',
                'message' => "Les coordonnées x/y doivent être valides pour le niveau de zoom $z"
            ], 400);
        }

        try {
            $url = "{$this->tilesUrl}/{$z}/{$x}/{$y}.pbf";

            Log::info("Requête tuile BDNB", [
                'zoom' => $z,
                'x' => $x,
                'y' => $y,
                'url' => $url
            ]);

            $client = new Client([
                'verify' => false,
                'timeout' => 30
            ]);

            $response = $client->get($url);

            return response($response->getBody())
                ->header('Content-Type', 'application/x-protobuf')
                ->header('Access-Control-Allow-Origin', '*');

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error("Erreur client lors de la récupération de la tuile", [
                'status' => $e->getResponse()->getStatusCode(),
                'body' => $e->getResponse()->getBody()->getContents()
            ]);

            return response()->json([
                'error' => 'Tuile non trouvée',
                'message' => 'Les coordonnées spécifiées ne correspondent à aucune tuile'
            ], 404);

        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération de la tuile", [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Erreur lors de la récupération de la tuile',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir toutes les informations d'un bâtiment (données + tuile)
     */
    public function getAllBuildingInfo(Request $request)
    {
        try {
            $request->validate([
                'building_id' => 'required|string',
                'z' => 'required|integer',
                'x' => 'required|integer',
                'y' => 'required|integer'
            ]);

            $client = new Client([
                'verify' => false,
                'timeout' => 30
            ]);

            // 1. Récupérer les données du bâtiment
            $buildingUrl = $this->searchUrl . '?batiment_groupe_id=eq.' . $request->building_id;
            $buildingResponse = $client->get($buildingUrl);
            $buildingData = json_decode($buildingResponse->getBody(), true);

            // 2. Récupérer la tuile correspondante
            $tileUrl = "{$this->tilesUrl}/{$request->z}/{$request->x}/{$request->y}.pbf";
            $tileResponse = $client->get($tileUrl);
            $tileData = $tileResponse->getBody();

            // 3. Combiner les résultats
            return response()->json([
                'building_data' => $buildingData,
                'tile_data' => base64_encode($tileData),
                'metadata' => [
                    'building_id' => $request->building_id,
                    'tile_coordinates' => [
                        'z' => $request->z,
                        'x' => $request->x,
                        'y' => $request->y
                    ],
                    'timestamp' => now()->toIso8601String()
                ]
            ]);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error("Erreur client lors de la récupération des données", [
                'status' => $e->getResponse()->getStatusCode(),
                'body' => $e->getResponse()->getBody()->getContents(),
                'building_id' => $request->building_id ?? null
            ]);

            return response()->json([
                'error' => 'Erreur lors de la récupération des données',
                'message' => $e->getMessage()
            ], $e->getResponse()->getStatusCode());

        } catch (\Exception $e) {
            Log::error("Erreur inattendue", [
                'message' => $e->getMessage(),
                'building_id' => $request->building_id ?? null
            ]);

            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifie si les coordonnées de la tuile sont valides
     */
    private function isValidTileCoordinate($z, $x, $y)
    {
        $maxCoord = pow(2, $z) - 1;
        return $x >= 0 && $x <= $maxCoord && $y >= 0 && $y <= $maxCoord;
    }

    
}
