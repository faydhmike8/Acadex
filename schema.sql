-- AcaDex - esquema completo da base de dados
-- Pode ser importado varias vezes: usa IF NOT EXISTS e nao apaga dados.

CREATE DATABASE IF NOT EXISTS acadex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE acadex;

-- 1. Utilizadores (o papel decide o que cada pessoa pode fazer)
CREATE TABLE IF NOT EXISTS utilizadores (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    utilizador  VARCHAR(50)  NOT NULL UNIQUE,
    email       VARCHAR(100) NOT NULL UNIQUE,
    senha_hash  VARCHAR(255) NOT NULL,
    papel       ENUM('admin', 'docente', 'estudante') NOT NULL DEFAULT 'estudante',
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Cursos: cada curso pertence a um docente.
-- ON DELETE CASCADE: se o docente for apagado, os seus cursos apagam-se tambem.
CREATE TABLE IF NOT EXISTS cursos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    titulo      VARCHAR(120) NOT NULL,
    descricao   TEXT,
    docente_id  INT NOT NULL,
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES utilizadores(id) ON DELETE CASCADE
);

-- 3. Inscricoes: liga estudantes a cursos (relacao muitos-para-muitos).
-- A chave primaria composta impede inscrever o mesmo estudante duas vezes.
CREATE TABLE IF NOT EXISTS inscricoes (
    estudante_id INT NOT NULL,
    curso_id     INT NOT NULL,
    inscrito_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (estudante_id, curso_id),
    FOREIGN KEY (estudante_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id)     REFERENCES cursos(id)       ON DELETE CASCADE
);

-- 4. Modulos: capitulos de um curso
CREATE TABLE IF NOT EXISTS modulos (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    curso_id  INT NOT NULL,
    titulo    VARCHAR(120) NOT NULL,
    ordem     INT NOT NULL DEFAULT 1,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
);

-- 5. Aulas: pertencem a um modulo
CREATE TABLE IF NOT EXISTS aulas (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    modulo_id  INT NOT NULL,
    titulo     VARCHAR(150) NOT NULL,
    video_url  VARCHAR(255),
    descricao  TEXT,
    ordem      INT NOT NULL DEFAULT 1,
    FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE
);

-- 6. Materiais (materia teorica): ficheiros de um curso, opcionalmente de um modulo.
-- ficheiro_original = nome que o docente enviou; ficheiro_guardado = nome aleatorio no disco.
CREATE TABLE IF NOT EXISTS materiais (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    curso_id           INT NOT NULL,
    modulo_id          INT NULL,
    titulo             VARCHAR(150) NOT NULL,
    ficheiro_original  VARCHAR(255) NOT NULL,
    ficheiro_guardado  VARCHAR(255) NOT NULL,
    criado_em          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (curso_id)  REFERENCES cursos(id)  ON DELETE CASCADE,
    FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE SET NULL
);

-- 7. Mensagens entre utilizadores
CREATE TABLE IF NOT EXISTS mensagens (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    remetente_id     INT NOT NULL,
    destinatario_id  INT NOT NULL,
    assunto          VARCHAR(150) NOT NULL,
    texto            TEXT NOT NULL,
    lida             TINYINT(1) NOT NULL DEFAULT 0,
    enviada_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (remetente_id)    REFERENCES utilizadores(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES utilizadores(id) ON DELETE CASCADE
);

-- 8. Tarefas de um curso
CREATE TABLE IF NOT EXISTS tarefas (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    curso_id   INT NOT NULL,
    titulo     VARCHAR(150) NOT NULL,
    enunciado  TEXT,
    prazo      DATETIME NULL,
    criada_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
);

-- 9. Entregas: um estudante entrega no maximo uma vez por tarefa (UNIQUE)
CREATE TABLE IF NOT EXISTS entregas (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    tarefa_id          INT NOT NULL,
    estudante_id       INT NOT NULL,
    ficheiro_original  VARCHAR(255) NOT NULL,
    ficheiro_guardado  VARCHAR(255) NOT NULL,
    comentario         TEXT NULL,
    entregue_em        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY entrega_unica (tarefa_id, estudante_id),
    FOREIGN KEY (tarefa_id)    REFERENCES tarefas(id)      ON DELETE CASCADE,
    FOREIGN KEY (estudante_id) REFERENCES utilizadores(id) ON DELETE CASCADE
);

-- 10. Notas: uma nota por entrega (escala 0 a 20)
CREATE TABLE IF NOT EXISTS notas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    entrega_id   INT NOT NULL UNIQUE,
    valor        DECIMAL(4,1) NOT NULL,
    comentario   TEXT NULL,
    avaliado_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entrega_id) REFERENCES entregas(id) ON DELETE CASCADE
);
