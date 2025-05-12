const jwt = require('jsonwebtoken');

const authMiddleware = (req, res, next) => {
  const authHeader = req.headers['authorization'];
  
  if (!authHeader) {
    console.log("No Authorization header");
    return res.status(401).json({ message: 'No token provided' });
  }

  const token = authHeader.split(' ')[1]; // Get token from "Bearer <token>"
  
  if (!token) {
    console.log("Invalid token format");
    return res.status(401).json({ message: 'Invalid token format' });
  }

  try {
    console.log("Verifying token...");
    const decoded = jwt.verify(token, 'your_jwt_secret'); // your secret must match
    console.log("Decoded token:", decoded);
    req.user = decoded;
    next();
  } catch (err) {
    console.error("Error verifying token:", err);
    return res.status(403).json({ message: 'Invalid token' });
  }
};

module.exports = authMiddleware;
