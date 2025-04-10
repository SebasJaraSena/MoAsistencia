```mermaid
flowchart TD
    A[INICIO] --> B[Encender el reproductor]
    B --> C{¿Está vacía la bandeja?}
    C -->|NO| D[Retirar DVD existente]
    C -->|SI| E[Insertar DVD deseado]
    D --> E
    E --> F[Presionar "play"]
    F --> G[FIN]
``` 