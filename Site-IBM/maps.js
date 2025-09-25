let map;

function initMap() {
    // Coordenadas padrão caso a geolocalização falhe
    const defaultPosition = { lat: -23.5505, lng: -46.6333 }; // Exemplo: São Paulo

    // Tenta obter a localização do usuário
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const userPosition = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                };
                createMap(userPosition);
            },
            () => {
                // Se a geolocalização falhar, usa a posição padrão
                createMap(defaultPosition);
            }
        );
    } else {
        // Se o navegador não suportar geolocalização, usa a posição padrão
        createMap(defaultPosition);
    }
}

function createMap(centerPosition) {
    map = new google.maps.Map(document.getElementById("map"), {
        center: centerPosition,
        zoom: 12,
    });

    // Lista de escolas para o exemplo
    const schools = [
        { lat: -23.561352, lng: -46.656094, name: "Escola de Música A" },
        { lat: -23.541457, lng: -46.645473, name: "Escola de Música B" },
        { lat: -23.557454, lng: -46.644143, name: "Escola de Música C" }
    ];

    // Cria os marcadores para cada escola
    schools.forEach(school => {
        new google.maps.Marker({
            position: { lat: school.lat, lng: school.lng },
            map: map,
            title: school.name,
        });
    });
}
