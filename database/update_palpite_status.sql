UPDATE palpites 
SET status = 'pago' 
WHERE jogador_id = ? 
AND bolao_id = ? 
AND id = (
    SELECT id 
    FROM (
        SELECT id 
        FROM palpites 
        WHERE jogador_id = ? 
        AND bolao_id = ? 
        ORDER BY data_palpite DESC 
        LIMIT 1
    ) as sub
); 