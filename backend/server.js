const express = require('express');
const cors = require('cors');         // 1. Import cors

const app = express();                // 2. Initialize app

const connectDB = require('./config/db');

// Connect to MongoDB
connectDB();

// Middleware
app.use(cors());                      // 3. Now apply cors
app.use(express.json());

// Routes
app.use('/api/users', require('./routes/users'));
app.listen(3000, () => console.log('Server is running on port 3000'));
app.use(cors());