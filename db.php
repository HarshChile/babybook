<?php
/**
 * Database Layer for MongoDB (with Graceful Fallback)
 * BabyBook / TinyTales
 */

require_once __DIR__ . '/config.php';

class DB {
    private static $manager = null;
    private static $useFallback = false;
    private static $checkedConnection = false;

    /**
     * Get or initialize MongoDB Manager
     */
    public static function getManager() {
        if (self::$checkedConnection) {
            return self::$manager;
        }

        self::$checkedConnection = true;

        if (!class_exists('MongoDB\Driver\Manager')) {
            self::$useFallback = true;
            return null;
        }

        try {
            $uri = MONGODB_URI;
            // Short connection timeout so initial check doesn't hang if server is not up
            $options = ['serverSelectionTimeoutMS' => 2000];
            $manager = new MongoDB\Driver\Manager($uri, $options);

            // Test ping to verify active MongoDB connection
            $command = new MongoDB\Driver\Command(['ping' => 1]);
            $manager->executeCommand(MONGODB_DATABASE, $command);

            self::$manager = $manager;
            self::$useFallback = false;
            return self::$manager;
        } catch (\Exception $e) {
            // MongoDB not reachable yet; fallback to local json file storage
            self::$useFallback = true;
            self::$manager = null;
            return null;
        }
    }

    /**
     * Check if currently using local fallback
     */
    public static function isFallback() {
        self::getManager();
        return self::$useFallback;
    }

    /* ============================================================
     * LOCAL JSON FALLBACK HELPERS
     * ============================================================ */
    private static function getFallbackPath($collection) {
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }
        return $dataDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $collection) . '.json';
    }

    private static function readFallbackData($collection) {
        $path = self::getFallbackPath($collection);
        if (!file_exists($path)) {
            return [];
        }
        $content = file_get_contents($path);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private static function writeFallbackData($collection, $data) {
        $path = self::getFallbackPath($collection);
        file_put_contents($path, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /* ============================================================
     * CRUD OPERATIONS
     * ============================================================ */

    /**
     * Insert a document into a collection
     * Returns generated ID as string
     */
    public static function insert($collection, array $document) {
        $manager = self::getManager();

        if ($manager && !self::$useFallback) {
            try {
                $bulk = new MongoDB\Driver\BulkWrite();
                if (!isset($document['_id'])) {
                    $id = new MongoDB\BSON\ObjectId();
                    $document['_id'] = $id;
                } else if (is_string($document['_id']) && strlen($document['_id']) === 24) {
                    $document['_id'] = new MongoDB\BSON\ObjectId($document['_id']);
                }
                $bulk->insert($document);
                $manager->executeBulkWrite(MONGODB_DATABASE . '.' . $collection, $bulk);
                return (string)$document['_id'];
            } catch (\Exception $e) {
                // In case of write failure, write to fallback
                self::$useFallback = true;
            }
        }

        // Fallback store
        $data = self::readFallbackData($collection);
        if (!isset($document['_id'])) {
            $document['_id'] = bin2hex(random_bytes(12));
        } else if ($document['_id'] instanceof MongoDB\BSON\ObjectId) {
            $document['_id'] = (string)$document['_id'];
        }
        $data[] = $document;
        self::writeFallbackData($collection, $data);
        return (string)$document['_id'];
    }

    /**
     * Find documents matching filter
     */
    public static function find($collection, array $filter = [], array $options = []) {
        $manager = self::getManager();

        if ($manager && !self::$useFallback) {
            try {
                // Normalize _id filter if needed
                if (isset($filter['_id']) && is_string($filter['_id']) && strlen($filter['_id']) === 24) {
                    try {
                        $filter['_id'] = new MongoDB\BSON\ObjectId($filter['_id']);
                    } catch (\Exception $ex) {}
                }

                $query = new MongoDB\Driver\Query($filter, $options);
                $cursor = $manager->executeQuery(MONGODB_DATABASE . '.' . $collection, $query);

                $results = [];
                foreach ($cursor as $doc) {
                    $item = json_decode(json_encode($doc), true);
                    if (isset($item['_id']) && is_array($item['_id']) && isset($item['_id']['$oid'])) {
                        $item['_id'] = $item['_id']['$oid'];
                    } elseif (isset($item['_id']) && is_object($item['_id'])) {
                        $item['_id'] = (string)$item['_id'];
                    }
                    $results[] = $item;
                }
                return $results;
            } catch (\Exception $e) {
                self::$useFallback = true;
            }
        }

        // Fallback filter
        $items = self::readFallbackData($collection);
        $filtered = [];
        foreach ($items as $item) {
            $match = true;
            foreach ($filter as $k => $v) {
                if (!isset($item[$k]) || $item[$k] != $v) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $filtered[] = $item;
            }
        }

        // Sort in fallback if sort option provided
        if (isset($options['sort']) && is_array($options['sort'])) {
            $sortField = array_key_first($options['sort']);
            $sortDir = $options['sort'][$sortField];
            usort($filtered, function ($a, $b) use ($sortField, $sortDir) {
                $va = $a[$sortField] ?? '';
                $vb = $b[$sortField] ?? '';
                return $sortDir < 0 ? strcmp($vb, $va) : strcmp($va, $vb);
            });
        }

        // Limit in fallback
        if (isset($options['limit']) && is_numeric($options['limit'])) {
            $filtered = array_slice($filtered, 0, (int)$options['limit']);
        }

        return $filtered;
    }

    /**
     * Find single document
     */
    public static function findOne($collection, array $filter = []) {
        $results = self::find($collection, $filter, ['limit' => 1]);
        return count($results) > 0 ? $results[0] : null;
    }

    /**
     * Update documents matching filter
     */
    public static function update($collection, array $filter, array $update, $multi = false) {
        $manager = self::getManager();

        if ($manager && !self::$useFallback) {
            try {
                if (isset($filter['_id']) && is_string($filter['_id']) && strlen($filter['_id']) === 24) {
                    try {
                        $filter['_id'] = new MongoDB\BSON\ObjectId($filter['_id']);
                    } catch (\Exception $ex) {}
                }
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->update($filter, ['$set' => $update], ['multi' => $multi]);
                $manager->executeBulkWrite(MONGODB_DATABASE . '.' . $collection, $bulk);
                return true;
            } catch (\Exception $e) {
                self::$useFallback = true;
            }
        }

        // Fallback update
        $items = self::readFallbackData($collection);
        $updated = false;
        foreach ($items as &$item) {
            $match = true;
            foreach ($filter as $k => $v) {
                if (!isset($item[$k]) || $item[$k] != $v) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                foreach ($update as $uk => $uv) {
                    $item[$uk] = $uv;
                }
                $updated = true;
                if (!$multi) break;
            }
        }
        self::writeFallbackData($collection, $items);
        return $updated;
    }

    /**
     * Delete document matching filter
     */
    public static function delete($collection, array $filter, $limit = 1) {
        $manager = self::getManager();

        if ($manager && !self::$useFallback) {
            try {
                if (isset($filter['_id']) && is_string($filter['_id']) && strlen($filter['_id']) === 24) {
                    try {
                        $filter['_id'] = new MongoDB\BSON\ObjectId($filter['_id']);
                    } catch (\Exception $ex) {}
                }
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->delete($filter, ['limit' => $limit]);
                $manager->executeBulkWrite(MONGODB_DATABASE . '.' . $collection, $bulk);
                return true;
            } catch (\Exception $e) {
                self::$useFallback = true;
            }
        }

        // Fallback delete
        $items = self::readFallbackData($collection);
        $newItems = [];
        $deleted = 0;
        foreach ($items as $item) {
            $match = true;
            foreach ($filter as $k => $v) {
                if (!isset($item[$k]) || $item[$k] != $v) {
                    $match = false;
                    break;
                }
            }
            if ($match && ($limit === 0 || $deleted < $limit)) {
                $deleted++;
                continue;
            }
            $newItems[] = $item;
        }
        self::writeFallbackData($collection, $newItems);
        return $deleted > 0;
    }

    /**
     * Count documents in collection
     */
    public static function count($collection, array $filter = []) {
        $manager = self::getManager();

        if ($manager && !self::$useFallback) {
            try {
                $command = new MongoDB\Driver\Command(['count' => $collection, 'query' => $filter]);
                $cursor = $manager->executeCommand(MONGODB_DATABASE, $command);
                $res = current($cursor->toArray());
                return $res->n ?? 0;
            } catch (\Exception $e) {
                self::$useFallback = true;
            }
        }

        return count(self::find($collection, $filter));
    }
}
